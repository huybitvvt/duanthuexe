<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\Accounting\JournalPostingService;
use App\Http\Services\Accounting\ReconciliationService;
use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\AccountingReconciliation;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingReconciliationTest extends TestCase
{
    protected $postingService;
    protected $reconciliationService;
    protected $user;
    protected $store;
    protected $cashAcc;
    protected $bankAcc;
    protected $revenueAcc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSchema();

        $this->postingService = new JournalPostingService();
        $this->reconciliationService = new ReconciliationService();

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $this->user = User::create([
            'name' => 'Kế toán trưởng',
            'email' => 'reconciler@himoto.vn',
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->store = Store::create([
            'store_name' => 'Himoto Cầu Giấy',
            'kind' => 'physical',
            'status' => 'active',
        ]);

        $this->cashAcc = AccountingAccount::create([
            'code' => '1111',
            'name' => 'Tiền mặt tại quỹ cơ sở',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $this->bankAcc = AccountingAccount::create([
            'code' => '1121',
            'name' => 'Tiền gửi ngân hàng công ty',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $this->revenueAcc = AccountingAccount::create([
            'code' => '5111',
            'name' => 'Doanh thu cho thuê xe ngắn hạn',
            'type' => 'revenue',
            'normal_balance' => 'credit',
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('accounting_reconciliations');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_accounts');
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

        Schema::create('accounting_accounts', function ($table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 30);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('normal_balance', 10)->default('debit');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
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

        Schema::create('journal_lines', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description', 500)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
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

    public function testCashReconciliationMatchesWhenBalancesAreEqual()
    {
        // Post cash receipt: 2,000,000 VND
        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Thu tiền mặt tại quỹ',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 2000000, 'credit' => 0, 'store_id' => $this->store->id],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 2000000, 'store_id' => $this->store->id],
            ],
        ], $this->user->id);

        $rec = $this->reconciliationService->reconcileCash(
            $this->store->id,
            '2026-09-17',
            2000000, // Kiểm đếm thực tế khớp sổ sách
            $this->user->id,
            'Kiểm đếm chốt ca'
        );

        $this->assertEquals('matched', $rec->status);
        $this->assertEquals(2000000, $rec->book_balance);
        $this->assertEquals(2000000, $rec->actual_balance);
        $this->assertEquals(0, $rec->difference);
    }

    public function testCashReconciliationDetectsDiscrepancy()
    {
        // Book balance is 2,000,000 VND
        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Thu tiền mặt tại quỹ',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 2000000, 'credit' => 0, 'store_id' => $this->store->id],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 2000000, 'store_id' => $this->store->id],
            ],
        ], $this->user->id);

        // Actual cash count is 1,950,000 (thiếu 50.000)
        $rec = $this->reconciliationService->reconcileCash(
            $this->store->id,
            '2026-09-17',
            1950000,
            $this->user->id,
            'Phát hiện thiếu 50.000 tiền mặt trong két'
        );

        $this->assertEquals('discrepancy', $rec->status);
        $this->assertEquals(2000000, $rec->book_balance);
        $this->assertEquals(1950000, $rec->actual_balance);
        $this->assertEquals(-50000, $rec->difference);
    }

    public function testApproveDiscrepancyUpdatesStatus()
    {
        $rec = AccountingReconciliation::create([
            'store_id' => $this->store->id,
            'account_type' => 'cash',
            'reconciliation_date' => '2026-09-17',
            'book_balance' => 2000000,
            'actual_balance' => 1950000,
            'difference' => -50000,
            'status' => 'discrepancy',
            'reconciled_by' => $this->user->id,
        ]);

        $approved = $this->reconciliationService->approveDiscrepancy(
            $rec->id,
            'Nhân viên ca trực nộp bù 50.000 theo quy định',
            $this->user->id
        );

        $this->assertEquals('approved', $approved->status);
        $this->assertStringContainsString('Nhân viên ca trực nộp bù 50.000', $approved->notes);
    }

    public function testTrialBalanceAndGeneralLedgerReports()
    {
        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Thu tiền gửi ngân hàng',
            'lines' => [
                ['account_id' => $this->bankAcc->id, 'debit' => 5000000, 'credit' => 0, 'store_id' => $this->store->id],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 5000000, 'store_id' => $this->store->id],
            ],
        ], $this->user->id);

        $trial = $this->reconciliationService->getTrialBalance('2026-09-01', '2026-09-30');
        $this->assertEquals(5000000, $trial['totals']['period_debit']);
        $this->assertEquals(5000000, $trial['totals']['period_credit']);

        $ledger = $this->reconciliationService->getGeneralLedger($this->bankAcc->id, '2026-09-01', '2026-09-30');
        $this->assertEquals(5000000, $ledger['closing_balance']);
        $this->assertCount(1, $ledger['movements']);
    }
}
