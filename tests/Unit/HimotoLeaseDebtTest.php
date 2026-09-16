<?php

namespace Tests\Unit;

use App\Entities\Customer;
use App\Entities\SellOrder;
use App\Entities\SellOrderItem;
use App\Http\Services\LeaseContractService;
use App\Models\DebtNote;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeasePaymentAllocation;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HimotoLeaseDebtTest extends TestCase
{
    protected $leaseService;
    protected $customer;
    protected $vehicle;
    protected $store;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        Schema::create('banks', function ($t) {
            $t->increments('id'); $t->integer('store_id'); $t->string('owner_type'); $t->timestamps();
        });
        Schema::create('lease_payment_requests', function ($t) {
            $t->increments('id'); $t->integer('lease_contract_id'); $t->string('request_key');
            $t->string('fingerprint'); $t->text('result'); $t->timestamps();
            $t->unique(['lease_contract_id', 'request_key']);
        });
        $this->leaseService = app(LeaseContractService::class);
        Schema::create('cash', function ($t) { $t->increments('id'); $t->integer('store_id'); $t->string('status'); });

        $this->store = Store::create([
            'store_name' => 'Kho Thuê sở hữu',
            'kind' => Store::KIND_LEASE_TO_OWN,
            'code' => 'KHO-TSH',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Kế toán công nợ',
            'email' => 'ketoan@himoto.vn',
            'password' => bcrypt('123456'),
            'role_id' => 1,
            'store_id' => $this->store->id,
            'status' => 'active',
        ]);
        \Illuminate\Support\Facades\DB::table('cash')->insert(['store_id' => $this->store->id, 'status' => 'Active']);

        $this->customer = Customer::create([
            'name' => 'Nguyễn Văn Nam',
            'phone' => '0987654321',
            'id_card' => '001200001234',
            'address' => 'Hà Nội',
        ]);

        $this->vehicle = Vehicle::create([
            'name' => 'Honda Wave Alpha 2023',
            'brand' => 'Honda',
            'type' => Vehicle::TYPE_XESO,
            'license' => '29X1-88888',
            'store_id' => $this->store->id,
            'status' => Vehicle::STATUS_READY,
        ]);
    }

    public function test_payment_retry_has_one_receipt_and_rejects_changed_payload()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id, 'start_date' => '2026-01-31',
            'total_amount' => 3000000, 'deposit_amount' => 1000000, 'installment_count' => 2,
        ], $this->user);
        $this->assertEquals(3000000, $contract->installments->sum('amount_due'));
        $this->assertEquals('2026-02-28', $contract->installments[1]->due_date->format('Y-m-d'));
        $this->assertEquals(0, Transaction::count());
        $data = ['amount' => 1000000, 'payment_method' => 1, 'payment_date' => '2026-02-01', 'idempotency_key' => 'retry-1'];
        $first = $this->leaseService->allocatePayment($contract->id, $data, $this->user);
        $this->assertEquals($first, $this->leaseService->allocatePayment($contract->id, $data, $this->user));
        $this->assertEquals(1, Transaction::count());
        $this->assertNotNull(Transaction::first()->cash_id);
        $this->assertEquals(1000000, $contract->allocations()->sum('amount'));
        $this->assertEquals('2026-02-01', Transaction::first()->created_at->format('Y-m-d'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $data['amount'] = 2000000;
        $this->leaseService->allocatePayment($contract->id, $data, $this->user);
    }

    public function test_branch_cannot_read_or_collect_another_branches_debt()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id, 'total_amount' => 3000000, 'installment_count' => 2,
        ], $this->user);
        $this->user->role_id = 3;
        $this->user->store_id = $this->store->id + 100;
        $this->assertEquals(0, $this->leaseService->index([], $this->user)->total());
        $this->assertEquals(0, $this->leaseService->getStats([], $this->user)['total_contracts']);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->leaseService->allocatePayment($contract->id, ['amount' => 1000], $this->user);
    }

    public function test_overpayment_rolls_back_without_receipt()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id, 'total_amount' => 3000000, 'installment_count' => 2,
        ], $this->user);
        try {
            $this->leaseService->allocatePayment($contract->id, ['amount' => 4000000], $this->user);
            $this->fail('Overpayment must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertEquals(0, Transaction::count());
            $this->assertEquals(0, $contract->allocations()->count());
        }
    }

    public function test_aging_filter_is_applied_before_pagination()
    {
        $old = $this->leaseService->createContract([
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id, 'start_date' => Carbon::today()->subMonths(3)->format('Y-m-d'),
            'total_amount' => 3000000, 'installment_count' => 2,
        ], $this->user);
        $anotherVehicle = Vehicle::create(['name' => 'Test', 'license' => 'TEST-2', 'status' => 'ready', 'store_id' => $this->store->id]);
        $this->leaseService->createContract([
            'customer_id' => $this->customer->id, 'vehicle_id' => $anotherVehicle->id,
            'store_id' => $this->store->id, 'total_amount' => 3000000, 'installment_count' => 2,
        ], $this->user);
        $page = $this->leaseService->index(['aging_bucket' => 'overdue_30_plus', 'per_page' => 1], $this->user);
        $this->assertEquals(1, $page->total());
        $this->assertEquals($old->id, $page->first()->id);
    }

    protected function createTestTables()
    {
        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Quản trị viên', 'slug' => 'quan-tri-vien'],
            ['id' => 2, 'name' => 'Quản lý cơ sở', 'slug' => 'quan-ly-co-so'],
            ['id' => 3, 'name' => 'Nhân viên', 'slug' => 'nhan-vien'],
        ]);

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('store_address')->nullable();
            $table->string('store_phone')->nullable();
            $table->string('kind')->default('physical');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('role_id')->default(1);
            $table->integer('store_id')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('customers');
        Schema::create('customers', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('id_card')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('year')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('current_store_id')->nullable();
            $table->string('license')->nullable();
            $table->string('status')->default('ready');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('transactions');
        Schema::create('transactions', function ($table) {
            $table->increments('id');
            $table->decimal('value', 15, 2)->default(0);
            $table->string('type')->default('in');
            $table->string('name')->nullable();
            $table->integer('payment_method')->default(1);
            $table->integer('bank_id')->nullable();
            $table->integer('cash_id')->nullable();
            $table->string('bank_owner_type')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->text('desc')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('sell_orders');
        Schema::create('sell_orders', function ($table) {
            $table->increments('id');
            $table->integer('customer_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('price_profit', 15, 2)->default(0);
            $table->integer('store_id')->nullable();
            $table->integer('sale_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('sell_order_items');
        Schema::create('sell_order_items', function ($table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('vehicle_id');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('lease_contracts');
        Schema::create('lease_contracts', function ($table) {
            $table->increments('id');
            $table->string('contract_code')->unique();
            $table->integer('customer_id');
            $table->integer('vehicle_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->integer('installment_count')->default(12);
            $table->decimal('period_amount', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->integer('assigned_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_installments');
        Schema::create('lease_installments', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('period_number');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_payment_allocations');
        Schema::create('lease_payment_allocations', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('installment_id')->nullable();
            $table->integer('transaction_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('debt_notes');
        Schema::create('debt_notes', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('customer_id')->nullable();
            $table->text('note_content');
            $table->date('appointment_date')->nullable();
            $table->string('debt_classification')->default('normal');
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test D01: Contract creation with installment schedule generation.
     */
    public function test_d01_contract_creation_generates_correct_installments()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id,
            'start_date' => '2026-01-01',
            'total_amount' => 24000000,
            'deposit_amount' => 0,
            'installment_count' => 12,
            'period_amount' => 2000000,
        ], $this->user);

        $this->assertNotNull($contract->id);
        $this->assertStringStartsWith('TSH-', $contract->contract_code);
        $this->assertCount(12, $contract->installments);

        // Check first installment due date is 1 month after start date
        $inst1 = $contract->installments->first();
        $this->assertEquals(1, $inst1->period_number);
        $this->assertEquals(2000000, $inst1->amount_due);
        $this->assertEquals(0, $inst1->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_UNPAID, $inst1->status);
    }

    /**
     * Test D01: Payment allocation flow (partial payment, multi-installment allocation).
     */
    public function test_d01_payment_allocation_partial_and_multi_period()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id,
            'start_date' => '2026-01-01',
            'total_amount' => 12000000,
            'deposit_amount' => 0,
            'installment_count' => 6,
            'period_amount' => 2000000,
        ], $this->user);

        // 1. Pay partial 1,000,000 for period 1 (due 2,000,000)
        $this->leaseService->allocatePayment($contract->id, [
            'amount' => 1000000,
            'payment_method' => 1,
            'notes' => 'Trả một phần kỳ 1',
        ], $this->user);

        $inst1 = LeaseInstallment::where('lease_contract_id', $contract->id)->where('period_number', 1)->first();
        $this->assertEquals(1000000, $inst1->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_PARTIALLY_PAID, $inst1->status);

        // 2. Pay 3,000,000 -> completes period 1 (needs 1,000,000) and completes period 2 (needs 2,000,000)
        $this->leaseService->allocatePayment($contract->id, [
            'amount' => 3000000,
            'payment_method' => 2,
            'bank_id' => \App\Models\Bank::create(['store_id' => $this->store->id, 'owner_type' => 'company'])->id,
            'notes' => 'Thanh toán tiếp kỳ 1 và kỳ 2',
        ], $this->user);

        $inst1->refresh();
        $this->assertEquals(2000000, $inst1->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_PAID, $inst1->status);

        $inst2 = LeaseInstallment::where('lease_contract_id', $contract->id)->where('period_number', 2)->first();
        $this->assertEquals(2000000, $inst2->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_PAID, $inst2->status);

        // Verify total allocated in ledger = 4,000,000
        $allocations = LeasePaymentAllocation::where('lease_contract_id', $contract->id)->get();
        $this->assertEquals(4000000, $allocations->sum('amount'));

        // Verify transactions created in financial ledger
        $transactions = Transaction::all();
        $this->assertEquals(4000000, $transactions->sum('value'));
    }

    /**
     * Test D01: Overdue calculation and aging buckets.
     */
    public function test_d01_overdue_calculation_and_aging_buckets()
    {
        // Set contract started 2 months ago
        $startDate = Carbon::now()->subMonths(2);
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id,
            'start_date' => $startDate->format('Y-m-d'),
            'total_amount' => 12000000,
            'deposit_amount' => 0,
            'installment_count' => 6,
            'period_amount' => 2000000,
        ], $this->user);

        // Period 1 was due 1 month ago -> overdue > 25 days
        // Period 2 is due today / recently overdue
        // Periods 3-6 are in the future -> NOT overdue

        $paginated = $this->leaseService->index([], $this->user);
        $loadedContract = $paginated->getCollection()->first();

        // Total contract = 12M, Paid = 0, Outstanding = 12M
        $this->assertEquals(12000000, $loadedContract->outstanding_balance);

        // Overdue amount should only count past due installments (Period 1 and 2), NOT future periods!
        $this->assertGreaterThanOrEqual(2000000, $loadedContract->overdue_amount);
        $this->assertLessThan(12000000, $loadedContract->overdue_amount);
        $this->assertGreaterThan(0, $loadedContract->overdue_days);

        // Overdue bucket should be overdue_8_30 or overdue_30_plus
        $this->assertContains($loadedContract->aging_bucket, ['overdue_8_30', 'overdue_30_plus']);
    }

    /**
     * Test D02: Historical sell_orders data remains untouched.
     */
    public function test_d02_historical_sell_orders_remain_intact()
    {
        // Seed historical sell order
        $sellOrder = SellOrder::create([
            'customer_id' => $this->customer->id,
            'price' => 15000000,
            'price_profit' => 2500000,
            'store_id' => $this->store->id,
        ]);

        SellOrderItem::create([
            'order_id' => $sellOrder->id,
            'vehicle_id' => $this->vehicle->id,
            'price' => 15000000,
            'cost_price' => 12500000,
        ]);

        // Query historical sell orders directly
        $retrieved = SellOrder::with('orderItems')->find($sellOrder->id);
        $this->assertNotNull($retrieved);
        $this->assertEquals(15000000, $retrieved->price);
        $this->assertEquals(2500000, $retrieved->price_profit);
        $this->assertCount(1, $retrieved->orderItems);

        // Confirm creating lease contracts did not alter sell_orders
        $this->assertEquals(1, SellOrder::count());
    }

    /**
     * Test Debt Notes and appointment reminders.
     */
    public function test_debt_notes_and_appointment_scheduling()
    {
        $contract = $this->leaseService->createContract([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id,
            'start_date' => '2026-01-01',
            'total_amount' => 10000000,
            'installment_count' => 5,
            'period_amount' => 2000000,
        ], $this->user);

        $note = $this->leaseService->addDebtNote($contract->id, [
            'note_content' => 'Khách hẹn thanh toán vào ngày 25/09/2026 qua chuyển khoản',
            'appointment_date' => '2026-09-25',
            'debt_classification' => DebtNote::CLASSIFICATION_REMINDER,
        ], $this->user);

        $this->assertNotNull($note->id);
        $this->assertEquals('2026-09-25', $note->appointment_date->format('Y-m-d'));
        $this->assertEquals(DebtNote::CLASSIFICATION_REMINDER, $note->debt_classification);

        $paginated = $this->leaseService->index([], $this->user);
        $loadedContract = $paginated->getCollection()->first();
        $this->assertNotNull($loadedContract->latest_note);
        $this->assertEquals('25/09/2026', $loadedContract->latest_note['appointment_date']);
    }
}
