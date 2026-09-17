<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\LeasePdfService;
use App\Models\AuditEvent;
use App\Entities\Customer;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeasePdfSnapshotTest extends TestCase
{
    protected $pdfService;
    protected $admin;
    protected $store;
    protected $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->pdfService = new LeasePdfService();

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin_pdf',
            'email' => 'admin_pdf@himoto.vn',
            'password' => bcrypt('secret'),
            'role_id' => $adminRole->id,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $this->store = Store::create([
            'name' => 'Cơ sở Cầu Giấy',
            'store_name' => 'Cơ sở Cầu Giấy',
            'address' => '123 Cầu Giấy, Hà Nội',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'name' => 'Nguyễn Văn A',
            'phone' => '0987654321',
            'id_card' => '001200001234',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'license' => '29B1-99999',
            'name' => 'VinFast Feliz S',
            'chassis' => 'CHASSIS123456',
            'engine' => 'ENG654321',
            'status' => 'rented',
            'store_id' => $this->store->id,
        ]);

        $this->contract = LeaseContract::create([
            'contract_code' => 'HDTS-2026-001',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'store_id' => $this->store->id,
            'total_amount' => 12000000,
            'deposit_amount' => 2000000,
            'period_amount' => 1000000,
            'installment_count' => 10,
            'status' => LeaseContract::STATUS_ACTIVE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-10-31',
        ]);

        for ($i = 1; $i <= 10; $i++) {
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

    public function test_number_to_vietnamese_words(): void
    {
        $this->assertEquals('Không đồng', LeasePdfService::numberToVietnameseWords(0));
        $this->assertEquals('Mười hai triệu đồng chẵn', LeasePdfService::numberToVietnameseWords(12000000));
        $this->assertEquals('Hai triệu năm trăm nghìn đồng chẵn', LeasePdfService::numberToVietnameseWords(2500000));
    }

    public function test_generate_contract_pdf_binary(): void
    {
        $pdf = $this->pdfService->generateContractPdf($this->contract, $this->admin);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', trim($pdf));
        $this->assertStringContainsString('HIMOTO ELECTRIC VEHICLES', $pdf);
        $this->assertStringContainsString('HDTS-2026-001', $pdf);
        $this->assertStringContainsString('29B1-99999', $pdf);
        $this->assertStringContainsString('VinFast Feliz S', $pdf);
        $this->assertStringContainsString('CHASSIS123456', $pdf);
        $this->assertStringContainsString('ENG654321', $pdf);

        // Verify audit event
        $audit = AuditEvent::where('action', 'lease.contract.pdf_export')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->admin->id, $audit->actor_user_id);
    }

    public function test_generate_debt_statement_pdf_binary(): void
    {
        $pdf = $this->pdfService->generateDebtStatementPdf($this->contract, $this->admin);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', trim($pdf));
        $this->assertStringContainsString('BANG DOI SOAT CONG NO THUE SO HUU', $pdf);

        // Verify audit event
        $audit = AuditEvent::where('action', 'lease.debt_statement.pdf_export')->first();
        $this->assertNotNull($audit);
    }

    public function test_contract_pdf_remains_strictly_immutable_after_customer_and_vehicle_modified(): void
    {
        // 1. Generate PDF lần đầu - snapshot được tự động chụp và khóa
        $pdf1 = $this->pdfService->generateContractPdf($this->contract, $this->admin);
        $this->contract->refresh();

        $originalHash = $this->contract->document_snapshot_hash;
        $this->assertNotEmpty($originalHash);
        $this->assertNotNull($this->contract->document_snapshot_locked_at);
        $this->assertStringContainsString('Nguyễn Văn A', json_encode($this->contract->document_snapshot, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('29B1-99999', json_encode($this->contract->document_snapshot, JSON_UNESCAPED_UNICODE));

        // 2. Thay đổi thông tin khách hàng và phương tiện trong CSDL sau này
        $customer = Customer::find($this->contract->customer_id);
        $customer->name = 'Tên Mới Đã Bị Sửa';
        $customer->phone = '0123456789';
        $customer->save();

        $vehicle = Vehicle::find($this->contract->vehicle_id);
        $vehicle->license = '30H-99999';
        $vehicle->name = 'Tên Xe Đã Đổi';
        $vehicle->save();

        $installment = LeaseInstallment::where('lease_contract_id', $this->contract->id)->first();
        $installment->amount_due = 987654321;
        $installment->due_date = '2030-12-31';
        $installment->save();

        // 3. Xuất lại PDF lần hai
        $pdf2 = $this->pdfService->generateContractPdf($this->contract, $this->admin);
        $this->contract->refresh();

        // Checksum và hash hoàn toàn không đổi
        $this->assertEquals($originalHash, $this->contract->document_snapshot_hash);

        // PDF lần hai vẫn chứa dữ liệu gốc từ snapshot bất biến
        $this->assertStringContainsString('Nguyen Van A', $pdf2);
        $this->assertStringContainsString('29B1-99999', $pdf2);
        $this->assertStringContainsString('VinFast Feliz S', $pdf2);

        // Tuyệt đối KHÔNG bị ảnh hưởng bởi dữ liệu sửa sau này
        $this->assertStringNotContainsString('Ten Moi Da Bi Sua', $pdf2);
        $this->assertStringNotContainsString('30H-99999', $pdf2);
        $this->assertStringNotContainsString('987.654.321', $pdf2);
        $this->assertStringNotContainsString('31/12/2030', $pdf2);
    }

    public function test_contract_pdf_rejects_tampered_snapshot(): void
    {
        $this->pdfService->generateContractPdf($this->contract, $this->admin);
        $this->contract->refresh();
        $snapshot = $this->contract->document_snapshot;
        $snapshot['financial_terms']['total_amount'] = 1;
        $this->contract->document_snapshot = $snapshot;
        $this->contract->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->pdfService->generateContractPdf($this->contract->fresh(), $this->admin);
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
            $table->integer('installment_count')->default(12);
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->longText('document_snapshot')->nullable();
            $table->string('document_snapshot_hash', 64)->nullable();
            $table->string('document_snapshot_version', 20)->default('1.0');
            $table->dateTime('document_snapshot_locked_at')->nullable();
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
            $table->integer('lease_installment_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('allocated_at');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
}
