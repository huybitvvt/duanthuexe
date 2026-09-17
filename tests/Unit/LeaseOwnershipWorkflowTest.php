<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\LeaseOwnershipService;
use App\Models\AuditEvent;
use App\Entities\Customer;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeaseOwnershipEvent;
use App\Models\LeaseOwnershipRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaseOwnershipWorkflowTest extends TestCase
{
    protected $service;
    protected $admin;
    protected $manager;
    protected $director;
    protected $store;
    protected $contract;
    protected $vehicle;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->service = new LeaseOwnershipService();

        $this->store = Store::create([
            'name' => 'Cơ sở Đống Đa',
            'store_name' => 'Cơ sở Đống Đa',
            'status' => 'active',
        ]);

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $managerRole = Role::create(['name' => 'Quản lý cơ sở', 'slug' => 'quan-ly-cua-hang']);
        $bodRole = Role::create(['name' => 'Ban Giám Đốc', 'slug' => 'ban-giam-doc']);

        $requestPermissionId = DB::table('permissions')->insertGetId([
            'name' => 'Yêu cầu chuyển quyền', 'slug' => 'lease.ownership_request',
        ]);
        $approvePermissionId = DB::table('permissions')->insertGetId([
            'name' => 'Duyệt chuyển quyền', 'slug' => 'lease.ownership_approve',
        ]);
        DB::table('roles_permissions')->insert([
            ['role_id' => $managerRole->id, 'permission_id' => $requestPermissionId],
            ['role_id' => $bodRole->id, 'permission_id' => $approvePermissionId],
        ]);

        $this->admin = User::create([
            'name' => 'Admin System',
            'username' => 'admin_own',
            'email' => 'admin_own@himoto.vn',
            'password' => bcrypt('secret'),
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->manager = User::create([
            'name' => 'Store Manager',
            'username' => 'manager_own',
            'email' => 'manager_own@himoto.vn',
            'password' => bcrypt('secret'),
            'role_id' => $managerRole->id,
            'is_admin' => 0,
            'store_id' => $this->store->id,
            'status' => 1,
        ]);
        $this->manager->stores()->attach($this->store->id);

        $this->director = User::create([
            'name' => 'Board Director',
            'username' => 'director_own',
            'email' => 'director_own@himoto.vn',
            'password' => bcrypt('secret'),
            'role_id' => $bodRole->id,
            'is_admin' => 0,
            'status' => 1,
        ]);

        $this->customer = Customer::create([
            'name' => 'Trần Thị B',
            'phone' => '0912345678',
            'id_card' => '001200005678',
            'status' => 'active',
        ]);

        $this->vehicle = Vehicle::create([
            'license' => '29D1-88888',
            'name' => 'VinFast Klara S',
            'status' => 'rented',
            'store_id' => $this->store->id,
        ]);

        $this->contract = LeaseContract::create([
            'contract_code' => 'HDTS-2026-888',
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'store_id' => $this->store->id,
            'total_amount' => 10000000,
            'deposit_amount' => 2000000,
            'period_amount' => 1000000,
            'installment_count' => 8,
            'status' => LeaseContract::STATUS_ACTIVE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-31',
        ]);

        LeaseInstallment::create([
            'lease_contract_id' => $this->contract->id,
            'period_number' => 0,
            'due_date' => '2026-01-01',
            'amount_due' => 2000000,
            'amount_paid' => 0,
            'status' => LeaseInstallment::STATUS_UNPAID,
        ]);

        for ($i = 1; $i <= 8; $i++) {
            LeaseInstallment::create([
                'lease_contract_id' => $this->contract->id,
                'period_number' => $i,
                'due_date' => '2026-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-15',
                'amount_due' => 1000000,
                'amount_paid' => 0,
                'status' => LeaseInstallment::STATUS_UNPAID,
            ]);
        }
    }

    public function test_cannot_execute_transfer_if_debt_remaining(): void
    {
        // Deposit is only a promised obligation until an allocation proves it was paid.
        $draft = $this->service->createDraft($this->contract->id, $this->admin);
        $this->assertEquals(LeaseOwnershipRequest::STATUS_DRAFT, $draft->status);
        $this->assertEquals(10000000, $draft->remaining_debt);

        // Cannot submit while the full 10m obligation remains unpaid.
        $this->expectException(ValidationException::class);
        $this->service->submitForApproval($draft->id, $this->admin, 'Nộp hồ sơ');
    }

    public function test_maker_checker_approval_rule(): void
    {
        // Simulate fully paid so it can be submitted
        foreach ($this->contract->installments as $inst) {
            $due = (float) ($inst->amount_due ?? $inst->amount);
            $inst->amount_paid = $due;
            $inst->status = LeaseInstallment::STATUS_PAID;
            $inst->save();

            $this->contract->allocations()->create([
                'installment_id' => $inst->id,
                'amount' => $due,
                'payment_date' => Carbon::now(),
                'status' => 'active',
            ]);
        }

        $draft = $this->service->createDraft($this->contract->id, $this->admin);
        $submitted = $this->service->submitForApproval($draft->id, $this->admin);

        // Admin who submitted cannot approve their own request (Maker-Checker violation)
        $this->expectException(ValidationException::class);
        $this->service->approve($submitted->id, $this->admin, 'Tự duyệt');
    }

    public function test_complete_transfer_lifecycle_when_fully_paid(): void
    {
        putenv('HIMOTO_ENABLE_OWNERSHIP_EXECUTE=true');

        try {
        // Simulate fully paid obligations, including period 0 deposit.
        foreach ($this->contract->installments as $inst) {
            $due = (float) ($inst->amount_due ?? $inst->amount);
            $inst->amount_paid = $due;
            $inst->status = LeaseInstallment::STATUS_PAID;
            $inst->save();

            // Create active payment allocation
            $this->contract->allocations()->create([
                'installment_id' => $inst->id,
                'amount' => $due,
                'payment_date' => Carbon::now(),
                'status' => 'active',
            ]);
        }

        // 1. Create Draft
        $req = $this->service->createDraft($this->contract->id, $this->manager, [
            'id_card_verified' => true,
            'contract_settled' => true,
            'vehicle_inspected' => true,
        ]);
        $this->assertEquals(LeaseOwnershipRequest::STATUS_DRAFT, $req->status);
        $this->assertEquals(0, $req->remaining_debt);

        // 2. Submit
        $submitted = $this->service->submitForApproval($req->id, $this->manager, 'Khách đã thanh toán đủ 100%');
        $this->assertEquals(LeaseOwnershipRequest::STATUS_SUBMITTED, $submitted->status);
        $this->assertEquals($this->manager->id, $submitted->requested_by);

        // 3. Approve by Director (different user)
        $approved = $this->service->approve($submitted->id, $this->director, 'Đồng ý chuyển quyền sở hữu');
        $this->assertEquals(LeaseOwnershipRequest::STATUS_APPROVED, $approved->status);
        $this->assertEquals($this->director->id, $approved->approved_by);

        // 4. Execute Transfer
        $executed = $this->service->executeTransfer($approved->id, $this->admin, 'Đã bàn giao xe và giấy tờ', 'IDEMP-TRANSFER-001');
        $this->assertEquals(LeaseOwnershipRequest::STATUS_EXECUTED, $executed->status);
        $this->assertEquals($this->admin->id, $executed->executed_by);

        // 5. Verify Vehicle status updated to transferred
        $this->vehicle->refresh();
        $this->assertEquals('transferred', $this->vehicle->status);

        // 6. Verify Contract status completed
        $this->contract->refresh();
        $this->assertEquals(LeaseContract::STATUS_COMPLETED, $this->contract->status);
        $this->assertStringContainsString('ĐÃ CHUYỂN QUYỀN SỞ HỮU XE', $this->contract->notes);

        // 7. Verify VehicleOwnership record created
        $ownership = VehicleOwnership::where('vehicle_id', $this->vehicle->id)->first();
        $this->assertNotNull($ownership);
        $this->assertEquals($this->customer->id, $ownership->customer_id);
        $this->assertEquals($this->contract->id, $ownership->lease_contract_id);
        $this->assertStringStartsWith('GCN-', $ownership->certificate_number);

        // 8. Verify immutable event audit trail
        $events = LeaseOwnershipEvent::where('ownership_request_id', $req->id)->get();
        $this->assertCount(4, $events); // draft, submitted, approved, executed

        // 9. Verify AuditEvent created
        $audit = AuditEvent::where('action', 'lease.ownership.execute')->first();
        $this->assertNotNull($audit);

        // 10. Verify Idempotency on retry
        $retry = $this->service->executeTransfer($approved->id, $this->admin, 'Thử lại', 'IDEMP-TRANSFER-001');
        $this->assertEquals($executed->id, $retry->id);
        } finally {
            putenv('HIMOTO_ENABLE_OWNERSHIP_EXECUTE');
        }
    }

    public function test_execute_transfer_is_blocked_when_feature_flag_is_disabled(): void
    {
        // 1. Setup fully paid request up to approved
        foreach ($this->contract->installments as $inst) {
            $due = (float) ($inst->amount_due ?? $inst->amount);
            $inst->amount_paid = $due;
            $inst->status = LeaseInstallment::STATUS_PAID;
            $inst->save();

            $this->contract->allocations()->create([
                'installment_id' => $inst->id,
                'amount' => $due,
                'payment_date' => Carbon::now(),
                'status' => 'active',
            ]);
        }

        $req = $this->service->createDraft($this->contract->id, $this->manager);
        $submitted = $this->service->submitForApproval($req->id, $this->manager);
        $approved = $this->service->approve($submitted->id, $this->director, 'Đã duyệt');

        // 2. Lock via feature flag
        putenv('HIMOTO_ENABLE_OWNERSHIP_EXECUTE=false');

        try {
            $this->service->executeTransfer($approved->id, $this->admin, 'Thực thi');
            $this->fail('Expected ValidationException when feature flag is disabled.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
            $this->assertArrayHasKey('feature_flag', $e->errors());
        } finally {
            putenv('HIMOTO_ENABLE_OWNERSHIP_EXECUTE');
        }
    }

    protected function createTestTables(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::create('audit_events', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->text('before_json')->nullable();
            $table->text('after_json')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('permissions');
        Schema::create('permissions', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('module')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('roles_permissions');
        Schema::create('roles_permissions', function ($table) {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->timestamps();
        });

        Schema::dropIfExists('users_permissions');
        Schema::create('users_permissions', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('permission_id');
            $table->timestamps();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('role_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('is_admin')->default(0);
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('store_name')->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('store_user');
        Schema::create('store_user', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('store_id');
        });

        Schema::dropIfExists('customers');
        Schema::create('customers', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('id_card')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('license')->nullable();
            $table->string('name')->nullable();
            $table->string('chassis')->nullable();
            $table->string('engine')->nullable();
            $table->string('status')->default('ready');
            $table->integer('store_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_contracts');
        Schema::create('lease_contracts', function ($table) {
            $table->increments('id');
            $table->string('contract_code')->nullable();
            $table->integer('customer_id');
            $table->integer('vehicle_id');
            $table->integer('store_id');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('period_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->integer('installment_count')->default(8);
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_installments');
        Schema::create('lease_installments', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('period_number')->nullable();
            $table->integer('installment_number')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::dropIfExists('lease_payment_allocations');
        Schema::create('lease_payment_allocations', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('installment_id')->nullable();
            $table->integer('lease_installment_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('payment_date')->nullable();
            $table->dateTime('allocated_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('lease_ownership_requests');
        Schema::create('lease_ownership_requests', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('vehicle_id');
            $table->integer('customer_id');
            $table->integer('store_id');
            $table->string('status', 30)->default('draft');
            $table->decimal('total_contract_amount', 15, 2)->default(0);
            $table->decimal('total_paid_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('remaining_debt', 15, 2)->default(0);
            $table->integer('unpaid_installments_count')->default(0);
            $table->text('checklist_documents')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('approval_reason', 500)->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->text('execution_notes')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_ownership_events');
        Schema::create('lease_ownership_events', function ($table) {
            $table->increments('id');
            $table->integer('ownership_request_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_ownerships');
        Schema::create('vehicle_ownerships', function ($table) {
            $table->increments('id');
            $table->integer('vehicle_id');
            $table->integer('customer_id');
            $table->integer('lease_contract_id')->nullable();
            $table->integer('ownership_request_id')->nullable();
            $table->dateTime('transferred_at');
            $table->string('certificate_number', 50)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
