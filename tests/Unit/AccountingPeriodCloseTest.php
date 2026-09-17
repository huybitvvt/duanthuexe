<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\Accounting\PeriodCloseService;
use App\Models\AccountingPeriod;
use App\Models\AccountingReconciliation;
use App\Models\JournalEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingPeriodCloseTest extends TestCase
{
    protected $service;
    protected $user;
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSchema();

        $this->service = new PeriodCloseService();

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $this->user = User::create([
            'name' => 'Kế toán trưởng',
            'email' => 'chief_accountant@himoto.vn',
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->store = Store::create([
            'store_name' => 'Himoto Cầu Giấy',
            'kind' => 'physical',
            'status' => 'active',
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('accounting_reconciliations');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('kind')->default('physical');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->integer('role_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('is_admin')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('audit_events', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_periods', function ($table) {
            $table->bigIncrements('id');
            $table->integer('fiscal_year');
            $table->integer('period_month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function ($table) {
            $table->bigIncrements('id');
            $table->string('entry_number', 50)->unique();
            $table->date('entry_date');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('posted');
            $table->string('description', 500);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reversed_entry_id')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('accounting_reconciliations', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('period_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('account_type', 20);
            $table->date('reconciliation_date');
            $table->decimal('book_balance', 15, 2)->default(0);
            $table->decimal('actual_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function testClosePeriodSuccessfully()
    {
        $period = $this->service->closePeriod(2026, 9, $this->user->id, 'Khóa sổ kỳ tháng 9/2026');

        $this->assertEquals('closed', $period->status);
        $this->assertEquals($this->user->id, $period->closed_by);
        $this->assertNotNull($period->closed_at);
        $this->assertStringContainsString('Khóa sổ kỳ tháng 9/2026', $period->notes);
    }

    public function testCannotRecloseAlreadyClosedPeriod()
    {
        $this->service->closePeriod(2026, 9, $this->user->id, 'Lần 1');

        $this->expectException(ValidationException::class);
        $this->service->closePeriod(2026, 9, $this->user->id, 'Lần 2');
    }

    public function testCannotClosePeriodWithDraftEntries()
    {
        // Create a draft entry in September 2026
        JournalEntry::create([
            'entry_number' => 'JE-DRAFT-001',
            'entry_date' => '2026-09-15',
            'status' => 'draft',
            'description' => 'Bút toán nháp chưa ghi sổ',
            'created_by' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->closePeriod(2026, 9, $this->user->id);
    }

    public function testCannotClosePeriodWithUnresolvedDiscrepancies()
    {
        $period = AccountingPeriod::create([
            'fiscal_year' => 2026,
            'period_month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        // Create an unresolved discrepancy reconciliation
        AccountingReconciliation::create([
            'period_id' => $period->id,
            'store_id' => $this->store->id,
            'account_type' => 'cash',
            'reconciliation_date' => '2026-09-30',
            'book_balance' => 10000000,
            'actual_balance' => 9800000,
            'difference' => -200000,
            'status' => 'discrepancy', // Chênh lệch chưa duyệt
        ]);

        $this->expectException(ValidationException::class);
        $this->service->closePeriod(2026, 9, $this->user->id);
    }

    public function testReopenPeriodRequiresReasonAndReopensSuccessfully()
    {
        $this->service->closePeriod(2026, 9, $this->user->id, 'Khóa sổ ban đầu');

        // Missing reason throws exception
        try {
            $this->service->reopenPeriod(2026, 9, $this->user->id, '');
            $this->fail('Expected ValidationException on empty reason.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Reopen with valid reason
        $reopened = $this->service->reopenPeriod(2026, 9, $this->user->id, 'Điều chỉnh hạch toán chi phí ngân hàng theo yêu cầu kiểm toán');

        $this->assertEquals('open', $reopened->status);
        $this->assertEquals($this->user->id, $reopened->reopened_by);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertStringContainsString('Điều chỉnh hạch toán chi phí ngân hàng', $reopened->notes);
    }
}
