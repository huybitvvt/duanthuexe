<?php

namespace Tests\Unit;

use App\Http\Services\CashRegisterService;
use App\Http\Services\HrService;
use App\Http\Services\LeaseContractService;
use App\Models\DailyCashRegister;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeasePaymentAllocation;
use App\Entities\Role;
use App\Models\StaffProfile;
use App\Models\Store;
use App\Models\StoreDutySchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Entities\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HimotoCashRegisterAndHrTest extends TestCase
{
    protected $cashRegisterService;
    protected $hrService;
    protected $leaseService;
    protected $adminUser;
    protected $staffUser;
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->cashRegisterService = app(CashRegisterService::class);
        $this->hrService = app(HrService::class);
        $this->leaseService = app(LeaseContractService::class);

        // Setup store
        $this->store = Store::create([
            'store_name' => 'Himoto Cầu Giấy',
            'store_address' => '123 Cầu Giấy, Hà Nội',
            'store_phone' => '0988111222',
            'kind' => Store::KIND_PHYSICAL,
        ]);

        // Setup admin role & user
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Quản trị viên',
            'display_name' => 'Quản trị viên',
            'slug' => 'admin',
        ]);
        $this->adminUser = User::create([
            'name' => 'Admin Himoto',
            'email' => 'admin@himoto.vn',
            'password' => bcrypt('secret123'),
            'role_id' => 1,
            'is_admin' => 1,
            'status' => 1,
        ]);

        // Staff user
        $this->staffUser = User::create([
            'name' => 'Nhân viên Cầu Giấy',
            'email' => 'staff@himoto.vn',
            'password' => bcrypt('secret123'),
            'role_id' => null,
            'is_admin' => 0,
            'store_id' => $this->store->id,
            'status' => 1,
        ]);
    }

    protected function createTestTables()
    {
        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

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
            $table->integer('role_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('is_admin')->default(0);
            $table->integer('status')->default(1);
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
            $table->integer('odometer')->default(0);
            $table->string('status')->default('ready');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('orders');
        Schema::create('orders', function ($table) {
            $table->increments('id');
            $table->string('code')->nullable();
            $table->string('contract_number')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_status')->default('1');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('transactions');
        Schema::create('transactions', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('order_item_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->integer('status')->default(1);
            $table->integer('user_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('payment_method')->nullable();
            $table->integer('bank_id')->nullable();
            $table->string('bank_owner_type')->nullable();
            $table->integer('cash_id')->nullable();
            $table->string('desc')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('banks');
        Schema::create('banks', function ($table) {
            $table->increments('id');
            $table->integer('store_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('cash');
        Schema::create('cash', function ($table) {
            $table->increments('id');
            $table->integer('store_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('daily_cash_registers');
        Schema::create('daily_cash_registers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('register_date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->integer('total_orders_count')->default(0);
            $table->decimal('deposit_cash', 15, 2)->default(0);
            $table->decimal('rental_cash', 15, 2)->default(0);
            $table->decimal('renewal_cash', 15, 2)->default(0);
            $table->decimal('refund_deposit_cash', 15, 2)->default(0);
            $table->decimal('penalty_cash', 15, 2)->default(0);
            $table->decimal('other_income_cash', 15, 2)->default(0);
            $table->decimal('other_expense_cash', 15, 2)->default(0);
            $table->decimal('deposit_bank_personal', 15, 2)->default(0);
            $table->decimal('rental_bank_personal', 15, 2)->default(0);
            $table->decimal('renewal_bank_personal', 15, 2)->default(0);
            $table->decimal('refund_deposit_bank_personal', 15, 2)->default(0);
            $table->decimal('penalty_bank_personal', 15, 2)->default(0);
            $table->decimal('other_expense_bank_personal', 15, 2)->default(0);
            $table->decimal('deposit_bank_company', 15, 2)->default(0);
            $table->decimal('rental_bank_company', 15, 2)->default(0);
            $table->decimal('renewal_bank_company', 15, 2)->default(0);
            $table->decimal('refund_deposit_bank_company', 15, 2)->default(0);
            $table->decimal('penalty_bank_company', 15, 2)->default(0);
            $table->decimal('other_expense_bank_company', 15, 2)->default(0);
            $table->decimal('system_cash_balance', 15, 2)->default(0);
            $table->decimal('actual_cash_counted', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->default(0);
            $table->text('difference_reason')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_contracts');
        Schema::create('lease_contracts', function ($table) {
            $table->bigIncrements('id');
            $table->string('contract_code')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('store_id');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->integer('installment_count');
            $table->decimal('period_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_installments');
        Schema::create('lease_installments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lease_contract_id');
            $table->integer('period_number');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_payment_allocations');
        Schema::create('lease_payment_allocations', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lease_contract_id');
            $table->unsignedBigInteger('installment_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->string('notes')->nullable();
            $table->string('status', 32)->default('active')->nullable();
            $table->unsignedBigInteger('reversal_transaction_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_payment_requests');
        Schema::create('lease_payment_requests', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->string('request_key');
            $table->string('fingerprint');
            $table->text('result');
            $table->timestamps();
        });

        Schema::dropIfExists('debt_notes');
        Schema::create('debt_notes', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lease_contract_id');
            $table->unsignedBigInteger('customer_id');
            $table->text('note_content');
            $table->date('appointment_date')->nullable();
            $table->string('debt_classification')->default('normal');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::dropIfExists('departments');
        Schema::create('departments', function ($table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('staff_profiles');
        Schema::create('staff_profiles', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('staff_code', 50)->nullable();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('id_card', 30)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('position', 100)->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('store_duty_schedules');
        Schema::create('store_duty_schedules', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id');
            $table->date('duty_date');
            $table->string('shift_name', 50)->default('Cả ngày');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('staff_name', 150);
            $table->string('staff_phone', 30)->nullable();
            $table->string('role_in_shift', 100)->default('Nhân viên trực');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('order_vehicle_details');
        Schema::create('order_vehicle_details', function ($table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('vehicle_id');
            $table->string('vehicle_name')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamp('rent_at')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_location_events');
        Schema::create('vehicle_location_events', function ($table) {
            $table->increments('id');
            $table->integer('vehicle_id');
            $table->integer('from_store_id')->nullable();
            $table->integer('to_store_id')->nullable();
            $table->string('event_type');
            $table->string('ref_type')->nullable();
            $table->integer('ref_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->integer('transfer_id')->nullable();
            $table->integer('odometer')->default(0);
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('contract_amendments');
        Schema::create('contract_amendments', function ($table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->string('amendment_code')->nullable();
            $table->string('amendment_type')->default('vehicle_exchange');
            $table->integer('old_vehicle_id')->nullable();
            $table->integer('new_vehicle_id')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->decimal('price_difference', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test 1: Daily Cash Register calculates correctly for Cash, Personal Bank, and Company Bank.
     */
    public function testDailyCashRegisterSummaryBreakdown()
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh')->toDateString();

        // Transaction 1: Thu tiền cọc bằng Tiền mặt (payment_method = 1)
        Transaction::create([
            'name' => 'Thu tiền cọc xe Honda Vision',
            'type' => Transaction::THU,
            'value' => 1000000,
            'payment_method' => 1,
            'bank_owner_type' => null,
            'store_id' => $this->store->id,
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh'),
        ]);

        // Transaction 2: Thu tiền thuê xe CK Cá nhân (payment_method = 2, bank_owner_type = personal)
        Transaction::create([
            'name' => 'Thu tiền thuê xe AirBlade',
            'type' => Transaction::THU,
            'value' => 500000,
            'payment_method' => 2,
            'bank_owner_type' => 'personal',
            'store_id' => $this->store->id,
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh'),
        ]);

        // Transaction 3: Thu tiền gia hạn CK Công ty (payment_method = 2, bank_owner_type = company)
        Transaction::create([
            'name' => 'Thu tiền gia hạn xe Lead',
            'type' => Transaction::THU,
            'value' => 700000,
            'payment_method' => 2,
            'bank_owner_type' => 'company',
            'store_id' => $this->store->id,
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh'),
        ]);

        // Transaction 4: Chi hoàn cọc bằng Tiền mặt (type = out, payment_method = 1)
        Transaction::create([
            'name' => 'Hoàn cọc xe cho khách',
            'type' => Transaction::CHI,
            'value' => 300000,
            'payment_method' => 1,
            'store_id' => $this->store->id,
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh'),
        ]);

        $summary = $this->cashRegisterService->getDailySummary($this->store->id, $today, $this->adminUser->id);

        $this->assertEquals(1000000, $summary['deposit_cash']);
        $this->assertEquals(300000, $summary['refund_deposit_cash']);
        $this->assertEquals(500000, $summary['rental_bank_personal']);
        $this->assertEquals(700000, $summary['renewal_bank_company']);

        // Tiền mặt dự tính = Opening (0) + Thu TM (1.000.000) - Chi/Hoàn TM (300.000) = 700.000
        $this->assertEquals(700000, $summary['system_cash_balance']);
        $this->assertEquals('open', $summary['status']);
    }

    /**
     * Test 2: Close daily register saves counted cash, difference, and enforces difference reason when mismatch.
     */
    public function testCloseDailyRegisterAndEnforceReasonOnMismatch()
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh')->toDateString();

        Transaction::create([
            'name' => 'Thu tiền thuê xe',
            'type' => Transaction::THU,
            'value' => 500000,
            'payment_method' => 1,
            'store_id' => $this->store->id,
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh'),
        ]);

        // Case A: Mismatch without reason throws Exception
        $this->expectException(\Exception::class);
        $this->cashRegisterService->closeDailyRegister(
            $this->store->id,
            $today,
            450000, // Thiếu 50.000 so với hệ thống 500.000
            '', // Không có lý do
            $this->adminUser->id
        );
    }

    /**
     * Test 3: Close daily register with reason succeeds and chains opening balance to next day.
     */
    public function testCloseDailyRegisterChainsOpeningBalance()
    {
        $day1 = '2026-09-15';
        $day2 = '2026-09-16';

        Transaction::create([
            'name' => 'Thu tiền cọc',
            'type' => Transaction::THU,
            'value' => 800000,
            'payment_method' => 1,
            'store_id' => $this->store->id,
            'created_at' => Carbon::parse($day1 . ' 10:00:00'),
        ]);

        // Chốt ngày 1 với số tiền thực đếm là 800.000
        $closedRegister = $this->cashRegisterService->closeDailyRegister(
            $this->store->id,
            $day1,
            800000,
            null,
            $this->adminUser->id,
            'Chốt két cuối ngày khớp tiền'
        );

        $this->assertEquals('closed', $closedRegister->status);
        $this->assertEquals(800000, $closedRegister->actual_cash_counted);
        $this->assertEquals(0, $closedRegister->cash_difference);

        // Kiểm tra ngày 2: Số dư đầu ngày phải tự động lấy 800.000 từ ngày 1
        $summaryDay2 = $this->cashRegisterService->getDailySummary($this->store->id, $day2, $this->adminUser->id);
        $this->assertEquals(800000, $summaryDay2['opening_balance']);
        $this->assertEquals(800000, $summaryDay2['system_cash_balance']);
    }

    /**
     * Test 4: Early settlement of Lease-to-own contract marks all installments paid and contract completed.
     */
    public function testLeaseContractEarlySettlement()
    {
        $ltoStore = Store::create([
            'store_name' => 'Kho Thuê Sở Hữu',
            'store_address' => 'Hà Nội',
            'kind' => Store::KIND_LEASE_TO_OWN,
        ]);
        DB::table('cash')->insert(['store_id' => $ltoStore->id, 'status' => 'Active']);

        $vehicle = Vehicle::create([
            'name' => 'VinFast Feliz S',
            'license' => '29X1-99999',
            'status' => Vehicle::STATUS_READY,
            'store_id' => $ltoStore->id,
            'current_store_id' => $ltoStore->id,
        ]);

        $customer = Customer::create([
            'name' => 'Nguyễn Văn Nam',
            'phone' => '0977888999',
        ]);

        // Tạo hợp đồng 12 triệu, cọc 0, 12 kỳ mỗi kỳ 1 triệu
        $contract = $this->leaseService->createContract([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'store_id' => $ltoStore->id,
            'total_amount' => 12000000,
            'deposit_amount' => 0,
            'installment_count' => 12,
            'period_amount' => 1000000,
            'start_date' => '2026-09-01',
        ], $this->adminUser);

        $this->assertEquals(12, $contract->installments()->count());
        $this->assertEquals(LeaseContract::STATUS_ACTIVE, $contract->status);

        // Khách tất toán sớm: trả 10 triệu dứt điểm, chiết khấu 2 triệu
        $settled = $this->leaseService->settleContract($contract->id, [
            'settlement_amount' => 10000000,
            'discount_amount' => 2000000,
            'payment_method' => 1,
            'note' => 'Khách tất toán sớm trước hạn',
        ], $this->adminUser);

        $this->assertEquals(LeaseContract::STATUS_COMPLETED, $settled->status);

        // Toàn bộ các kỳ phải chuyển trạng thái PAID
        $unpaidCount = LeaseInstallment::where('lease_contract_id', $contract->id)
            ->where('status', '!=', LeaseInstallment::STATUS_PAID)
            ->count();
        $this->assertEquals(0, $unpaidCount);
    }

    /**
     * Test 5: Reversal (Đảo thu) restores installment amount paid.
     */
    public function testLeasePaymentReversal()
    {
        $ltoStore = Store::create([
            'store_name' => 'Kho Thuê Sở Hữu 2',
            'kind' => Store::KIND_LEASE_TO_OWN,
        ]);
        DB::table('cash')->insert(['store_id' => $ltoStore->id, 'status' => 'Active']);

        $vehicle = Vehicle::create([
            'name' => 'Evo 200',
            'license' => '29X2-88888',
            'status' => Vehicle::STATUS_READY,
            'store_id' => $ltoStore->id,
        ]);

        $customer = Customer::create([
            'name' => 'Trần Thị Mai',
            'phone' => '0912345678',
        ]);

        $contract = $this->leaseService->createContract([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'store_id' => $ltoStore->id,
            'total_amount' => 6000000,
            'deposit_amount' => 0,
            'installment_count' => 6,
            'period_amount' => 1000000,
            'start_date' => '2026-09-01',
        ], $this->adminUser);

        // Thu kỳ 1: 1.000.000
        $payResult = $this->leaseService->allocatePayment($contract->id, [
            'amount' => 1000000,
            'payment_date' => '2026-09-02',
            'payment_method' => 1,
        ], $this->adminUser);

        $firstInst = $contract->installments()->orderBy('period_number')->first();
        $this->assertEquals(1000000, $firstInst->fresh()->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_PAID, $firstInst->fresh()->status);

        $allocation = LeasePaymentAllocation::where('installment_id', $firstInst->id)->first();
        $this->assertNotNull($allocation);

        // Đảo thu do hạch toán nhầm
        $rev = $this->leaseService->reverseAllocation($allocation->id, 'Hạch toán nhầm hợp đồng của khách khác', $this->adminUser);
        $this->assertTrue($rev['success']);

        // Kỳ 1 phải trở lại 0 và status unpaid
        $this->assertEquals(0, $firstInst->fresh()->amount_paid);
        $this->assertEquals(LeaseInstallment::STATUS_UNPAID, $firstInst->fresh()->status);
    }

    /**
     * Test 6: HR Staff Profile and Store Duty Schedule lookup.
     */
    public function testStaffAndStoreDutySchedule()
    {
        $staff = $this->hrService->saveStaffProfile([
            'full_name' => 'Lê Văn Trọng',
            'phone' => '0933444555',
            'position' => 'Trưởng ca cửa hàng',
            'store_id' => $this->store->id,
        ]);

        $this->assertNotEmpty($staff->staff_code);
        $this->assertEquals('Lê Văn Trọng', $staff->full_name);

        $today = Carbon::today('Asia/Ho_Chi_Minh')->toDateString();

        // Phân ca trực hôm nay
        $schedule = $this->hrService->saveStoreDutySchedule([
            'store_id' => $this->store->id,
            'duty_date' => $today,
            'shift_name' => 'Ca sáng (08h00 - 15h00)',
            'staff_id' => $staff->id,
            'staff_name' => $staff->full_name,
            'staff_phone' => $staff->phone,
            'role_in_shift' => 'Trưởng ca trực',
            'notes' => 'Trực bàn giao xe & két tiền sáng',
        ], $this->adminUser->id);

        $this->assertNotNull($schedule->id);

        // Tra cứu lịch trực hôm nay theo cơ sở
        $roster = $this->hrService->getStoreDutySchedules($today, $this->store->id);
        $this->assertCount(1, $roster);
        $this->assertEquals('Himoto Cầu Giấy', $roster[0]['store_name']);
        $this->assertCount(1, $roster[0]['schedules']);
        $this->assertEquals('Lê Văn Trọng', $roster[0]['schedules'][0]['staff_name']);
        $this->assertEquals('0933444555', $roster[0]['schedules'][0]['staff_phone']);
    }
}
