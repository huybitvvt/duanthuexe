<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Controllers\KpiReportController;
use App\Http\Services\AuditService;
use App\Http\Services\KpiReportService;
use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\User;
use App\Support\PermissionAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    protected $store1;
    protected $store2;
    protected $adminUser;
    protected $bodUser;
    protected $accountantUser;
    protected $hrUser;
    protected $staffUser1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSchema();

        $this->store1 = Store::create([
            'store_name' => 'Himoto Cầu Giấy',
            'kind' => 'physical',
            'status' => 'active'
        ]);

        $this->store2 = Store::create([
            'store_name' => 'Himoto Đống Đa',
            'kind' => 'physical',
            'status' => 'active'
        ]);

        // Roles
        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $bodRole = Role::create(['name' => 'Ban Giám Đốc', 'slug' => 'ban-giam-doc']);
        $accRole = Role::create(['name' => 'Kế Toán', 'slug' => 'ke-toan']);
        $hrRole = Role::create(['name' => 'Nhân Sự', 'slug' => 'nhan-su']);
        $staffRole = Role::create(['name' => 'Nhân Viên', 'slug' => 'nhan-vien']);

        $this->adminUser = User::create([
            'name' => 'Admin Himoto',
            'email' => 'admin@himoto.vn',
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->bodUser = User::create([
            'name' => 'Giám Đốc Himoto',
            'email' => 'bod@himoto.vn',
            'role_id' => $bodRole->id,
            'is_admin' => 0,
            'status' => 1,
        ]);

        $this->accountantUser = User::create([
            'name' => 'Kế Toán Trưởng',
            'email' => 'ketoan@himoto.vn',
            'role_id' => $accRole->id,
            'is_admin' => 0,
            'status' => 1,
        ]);

        $this->hrUser = User::create([
            'name' => 'Trưởng Phòng Nhân Sự',
            'email' => 'hr@himoto.vn',
            'role_id' => $hrRole->id,
            'is_admin' => 0,
            'status' => 1,
        ]);

        $this->staffUser1 = User::create([
            'name' => 'Nhân Viên Cơ Sở 1',
            'email' => 'staff1@himoto.vn',
            'role_id' => $staffRole->id,
            'store_id' => $this->store1->id,
            'is_admin' => 0,
            'status' => 1,
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('kind')->default('physical');
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

        Schema::dropIfExists('audit_events');
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
            $table->timestamp('created_at')->nullable();
        });
    }

    public function testAdminHasFullCapabilitiesAcrossAllStores(): void
    {
        $this->assertTrue(PermissionAccess::isAdmin($this->adminUser));
        $this->assertTrue(PermissionAccess::allows($this->adminUser, 'kpi.view_company'));
        $this->assertTrue(PermissionAccess::allows($this->adminUser, 'accounting.post'));
        $this->assertTrue(PermissionAccess::allows($this->adminUser, 'hr.manage_staff'));
        $this->assertTrue(PermissionAccess::allows($this->adminUser, 'lease.view', $this->store1->id));
        $this->assertTrue(PermissionAccess::allows($this->adminUser, 'lease.view', $this->store2->id));
        $this->assertEquals(['*'], PermissionAccess::capabilities($this->adminUser));
    }

    public function testBoardOfDirectorsCanViewCompanyKpiAndCannotPostJournals(): void
    {
        $this->assertFalse(PermissionAccess::isAdmin($this->bodUser));
        $this->assertTrue(PermissionAccess::allows($this->bodUser, 'kpi.view_company'));
        $this->assertTrue(PermissionAccess::allows($this->bodUser, 'accounting.view'));
        $this->assertTrue(PermissionAccess::allows($this->bodUser, 'hr.view'));
        $this->assertTrue(PermissionAccess::allows($this->bodUser, 'lease.ownership_approve'));

        // BGĐ không tự sửa/post bút toán
        $this->assertFalse(PermissionAccess::allows($this->bodUser, 'accounting.post'));
        $this->assertFalse(PermissionAccess::allows($this->bodUser, 'accounting.reverse'));
        $this->assertFalse(PermissionAccess::allows($this->bodUser, 'hr.manage_staff'));
    }

    public function testAccountantCanManageAccountingAndCannotManageHrOrGpsRecovery(): void
    {
        $this->assertTrue(PermissionAccess::allows($this->accountantUser, 'accounting.view'));
        $this->assertTrue(PermissionAccess::allows($this->accountantUser, 'accounting.post'));
        $this->assertTrue(PermissionAccess::allows($this->accountantUser, 'accounting.close_period'));
        $this->assertTrue(PermissionAccess::allows($this->accountantUser, 'accounting.reconcile'));

        // Kế toán không được quản lý nhân sự hay thu hồi GPS
        $this->assertFalse(PermissionAccess::allows($this->accountantUser, 'hr.manage_staff'));
        $this->assertFalse(PermissionAccess::allows($this->accountantUser, 'gps.recovery_action'));
    }

    public function testHrStaffCanManageHrAndCannotAccessAccountingOrCompanyKpi(): void
    {
        $this->assertTrue(PermissionAccess::allows($this->hrUser, 'hr.view'));
        $this->assertTrue(PermissionAccess::allows($this->hrUser, 'hr.manage_staff'));
        $this->assertTrue(PermissionAccess::allows($this->hrUser, 'hr.manage_schedule'));

        // HR không xem kế toán hay KPI toàn công ty
        $this->assertFalse(PermissionAccess::allows($this->hrUser, 'accounting.view'));
        $this->assertFalse(PermissionAccess::allows($this->hrUser, 'kpi.view_company'));
    }

    public function testStaffCannotAccessOtherStoreData(): void
    {
        // Nhân viên thuộc store 1 xem được store 1
        $this->assertTrue(PermissionAccess::allows($this->staffUser1, 'lease.view', $this->store1->id));

        // Nhân viên thuộc store 1 KHÔNG được xem store 2
        $this->assertFalse(PermissionAccess::allows($this->staffUser1, 'lease.view', $this->store2->id));

        // Nhân viên không được xem KPI toàn công ty
        $this->assertFalse(PermissionAccess::allows($this->staffUser1, 'kpi.view_company'));
    }

    public function testKpiReportControllerRejectsStaffWithoutPermission(): void
    {
        Auth::setUser($this->staffUser1);
        $service = $this->createMock(KpiReportService::class);
        $controller = new KpiReportController($service);

        // Staff gọi xem KPI cơ sở 2 -> 403
        $req = Request::create('/kpi', 'GET', ['store_id' => $this->store2->id]);
        $response = $controller->index($req);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCheckPermissionMiddlewareReturnsStructured403ForCrossStoreAccess(): void
    {
        $middleware = new \App\Http\Middleware\CheckPermission();

        // Staff 1 cố truy cập tài nguyên Store 2
        $req = Request::create('/lease-contracts', 'GET', ['store_id' => $this->store2->id]);
        $req->setUserResolver(function () {
            return $this->staffUser1;
        });

        $response = $middleware->handle($req, function () {
            return response()->json(['status' => 'success']);
        }, 'lease.view');

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertEquals('lease.view', $data['required_permission']);
    }

    public function testBodCanViewAccountingButCannotPostJournalEntry(): void
    {
        // BGĐ được xem kế toán
        $this->assertTrue(PermissionAccess::allows($this->bodUser, 'accounting.view'));

        // BGĐ KHÔNG có quyền post hạch toán
        $this->assertFalse(PermissionAccess::allows($this->bodUser, 'accounting.post'));

        // Gọi can() phải ném AuthorizationException
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        PermissionAccess::can($this->bodUser, 'accounting.post');
    }

    public function testAccountantCannotModifyHrStaff(): void
    {
        $this->assertFalse(PermissionAccess::allows($this->accountantUser, 'hr.manage_staff'));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        PermissionAccess::can($this->accountantUser, 'hr.manage_staff');
    }

    public function testHrUserCannotViewAccounting(): void
    {
        $this->assertFalse(PermissionAccess::allows($this->hrUser, 'accounting.view'));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        PermissionAccess::can($this->hrUser, 'accounting.view');
    }

    public function testAuditServiceSanitizesDataAndStoresEvent(): void
    {
        $raw = [
            'password' => 'secret123456',
            'token' => 'jwt.token.here',
            'id_card' => '001095012345',
            'amount' => 5000000,
        ];

        $sanitized = AuditService::sanitizeData($raw);
        $this->assertEquals('***REDACTED***', $sanitized['password']);
        $this->assertEquals('***REDACTED***', $sanitized['token']);
        $this->assertEquals('0010********', $sanitized['id_card']);
        $this->assertEquals('******4321', AuditService::sanitizeData(['phone' => '0987654321'])['phone']);
        $this->assertEquals(5000000, $sanitized['amount']);

        // Ghi event vào DB
        $event = AuditService::log(
            'lease.contract.create',
            'App\\Models\\LeaseContract',
            null,
            $raw,
            'Tạo hợp đồng thuê mua',
            $this->store1->id,
            $this->staffUser1->id
        );

        $this->assertNotNull($event);
        $this->assertEquals('lease.contract.create', $event->action);
        $this->assertEquals($this->staffUser1->id, $event->actor_user_id);
        $this->assertEquals('0010********', $event->after_json['id_card']);
        $this->assertEquals('***REDACTED***', $event->after_json['password']);
        $this->assertNotEmpty($event->request_id);
        $this->assertNotEmpty($event->ip_hash);
    }
}
