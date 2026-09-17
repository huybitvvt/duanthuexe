<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\Accounting\JournalPostingService;
use App\Http\Services\Accounting\JournalReversalService;
use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalPostingInvariantTest extends TestCase
{
    protected $postingService;
    protected $reversalService;
    protected $user;
    protected $store;
    protected $cashAcc;
    protected $revenueAcc;
    protected $receivableAcc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSchema();

        $this->postingService = new JournalPostingService();
        $this->reversalService = new JournalReversalService($this->postingService);

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $this->user = User::create([
            'name' => 'Kế toán viên',
            'email' => 'accountant@himoto.vn',
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->store = Store::create([
            'store_name' => 'Himoto Cầu Giấy',
            'kind' => 'physical',
            'status' => 'active',
        ]);

        // Seed core accounts
        $this->cashAcc = AccountingAccount::create([
            'code' => '1111',
            'name' => 'Tiền mặt tại quỹ cơ sở',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $this->revenueAcc = AccountingAccount::create([
            'code' => '5111',
            'name' => 'Doanh thu cho thuê xe ngắn hạn',
            'type' => 'revenue',
            'normal_balance' => 'credit',
        ]);

        $this->receivableAcc = AccountingAccount::create([
            'code' => '1312',
            'name' => 'Phải thu hợp đồng thuê sở hữu',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
    }

    protected function setupSchema(): void
    {
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
    }

    public function testDebitEqualsCreditInvariantPassesAndPersistsLines()
    {
        $entry = $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Thu tiền thuê xe trực tiếp tại quầy',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 500000],
            ],
        ], $this->user->id);

        $this->assertNotNull($entry->id);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(500000, $entry->total_debit);
        $this->assertEquals(500000, $entry->total_credit);
        $this->assertCount(2, $entry->lines);
        $this->assertStringStartsWith('JE-202609-', $entry->entry_number);
    }

    public function testUnbalancedEntryThrowsValidationException()
    {
        $this->expectException(ValidationException::class);

        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Bút toán lệch Nợ Có',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 450000], // lệch 50.000
            ],
        ], $this->user->id);
    }

    public function testLessThanTwoLinesThrowsValidationException()
    {
        $this->expectException(ValidationException::class);

        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Chỉ có 1 dòng',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 500000, 'credit' => 0],
            ],
        ], $this->user->id);
    }

    public function testLineCannotHaveBothDebitAndCredit()
    {
        $this->expectException(ValidationException::class);

        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Một dòng có cả Nợ và Có',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 500000, 'credit' => 100000],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 400000],
            ],
        ], $this->user->id);
    }

    public function testNegativeAmountsThrowValidationException()
    {
        $this->expectException(ValidationException::class);

        $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Số tiền âm',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => -500000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => -500000],
            ],
        ], $this->user->id);
    }

    public function testPostIdempotencyReturnsSameEntryWithoutDuplicates()
    {
        $key = 'TX-IDEMPOTENT-20260917-001';

        $entry1 = $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Giao dịch lần 1',
            'idempotency_key' => $key,
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 1000000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 1000000],
            ],
        ], $this->user->id);

        $entry2 = $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Gửi lại cùng key',
            'idempotency_key' => $key,
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 1000000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 1000000],
            ],
        ], $this->user->id);

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertEquals(1, JournalEntry::where('idempotency_key', $key)->count());
        $this->assertEquals(2, JournalLine::where('journal_entry_id', $entry1->id)->count());
    }

    public function testClosedPeriodBlocksPosting()
    {
        // Close period August 2026
        AccountingPeriod::create([
            'fiscal_year' => 2026,
            'period_month' => 8,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'closed',
        ]);

        $this->expectException(ValidationException::class);

        $this->postingService->post([
            'entry_date' => '2026-08-15', // Ngày trong kỳ đã đóng
            'store_id' => $this->store->id,
            'description' => 'Cố tình ghi sổ lùi vào kỳ đã khóa',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 200000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 200000],
            ],
        ], $this->user->id);
    }

    public function testReversalSwapsDebitsAndCreditsAndMarksOriginalReversed()
    {
        $original = $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Bút toán gốc ghi nhầm',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 750000, 'credit' => 0],
                ['account_id' => $this->receivableAcc->id, 'debit' => 0, 'credit' => 750000],
            ],
        ], $this->user->id);

        $this->assertEquals('posted', $original->status);

        $reversal = $this->reversalService->reverse(
            $original->id,
            'Phát hiện ghi nhầm số tiền, đảo bút toán theo biên bản',
            $this->user->id,
            '2026-09-17'
        );

        $this->assertNotNull($reversal->id);
        $this->assertNotEquals($original->id, $reversal->id);
        $this->assertEquals('posted', $reversal->status);
        $this->assertEquals($original->id, $reversal->reversed_entry_id);

        // Check original entry is updated to reversed
        $originalRefreshed = $original->fresh();
        $this->assertEquals('reversed', $originalRefreshed->status);

        // Check lines swapped:
        // Cash acc line had debit 750000, now should have credit 750000
        $revCashLine = $reversal->lines->where('account_id', $this->cashAcc->id)->first();
        $this->assertEquals(0, $revCashLine->debit);
        $this->assertEquals(750000, $revCashLine->credit);

        // Receivable acc line had credit 750000, now should have debit 750000
        $revRecLine = $reversal->lines->where('account_id', $this->receivableAcc->id)->first();
        $this->assertEquals(750000, $revRecLine->debit);
        $this->assertEquals(0, $revRecLine->credit);

        // Invariant still holds: sum(debit) == sum(credit)
        $this->assertEquals(750000, $reversal->total_debit);
        $this->assertEquals(750000, $reversal->total_credit);
    }

    public function testCannotReverseAlreadyReversedEntry()
    {
        $original = $this->postingService->post([
            'entry_date' => '2026-09-17',
            'store_id' => $this->store->id,
            'description' => 'Bút toán đảo 1 lần',
            'lines' => [
                ['account_id' => $this->cashAcc->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $this->revenueAcc->id, 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->user->id);

        $this->reversalService->reverse($original->id, 'Lý do 1', $this->user->id);

        $this->expectException(ValidationException::class);
        $this->reversalService->reverse($original->id, 'Cố tình đảo lần 2', $this->user->id);
    }
}
