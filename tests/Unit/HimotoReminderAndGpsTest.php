<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Entities\Customer;
use App\Models\CustomerReminderOutbox;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Http\Services\CustomerReminderService;
use App\Http\Services\Gps\GpsService;
use App\Http\Services\Gps\MockGpsProvider;
use App\Repositories\OrderRepositoryEloquent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class HimotoReminderAndGpsTest extends TestCase
{
    protected $reminderService;
    protected $gpsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();
        $this->reminderService = app(CustomerReminderService::class);
        $this->gpsService = new GpsService(new MockGpsProvider());
    }

    protected function createTestTables()
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
            \Illuminate\Support\Facades\DB::table('roles')->insert([
                ['id' => 1, 'name' => 'Quản trị viên', 'slug' => 'quan-tri-vien'],
                ['id' => 2, 'name' => 'Quản lý cơ sở', 'slug' => 'quan-ly-co-so'],
            ]);
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('email')->nullable();
                $table->unsignedBigInteger('role_id')->default(1);
                $table->unsignedBigInteger('store_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('store_name');
                $table->string('store_code')->nullable();
                $table->string('kind', 30)->default('physical');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('id_card')->nullable();
                $table->string('address')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('license');
                $table->string('status')->default('ready');
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('current_store_id')->nullable();
                $table->boolean('in_transit')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lease_contracts')) {
            Schema::create('lease_contracts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('contract_code')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('vehicle_id');
                $table->unsignedBigInteger('store_id');
                $table->date('start_date');
                $table->decimal('total_amount', 15, 2);
                $table->decimal('deposit_amount', 15, 2)->default(0);
                $table->unsignedInteger('installment_count');
                $table->decimal('period_amount', 15, 2);
                $table->decimal('total_paid', 15, 2)->default(0);
                $table->decimal('remaining_debt', 15, 2)->default(0);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('lease_installments')) {
            Schema::create('lease_installments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lease_contract_id');
                $table->unsignedInteger('period_number');
                $table->date('due_date');
                $table->decimal('amount_due', 15, 2);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->string('status')->default('unpaid');
                $table->date('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('contract_number')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->string('order_status')->default('renting');
                $table->unsignedInteger('out_dated_at')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('order_vehicle_details')) {
            Schema::create('order_vehicle_details', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->dateTime('rent_at')->nullable();
                $table->dateTime('return_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('type')->default('in');
                $table->decimal('value', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_reminder_outbox')) {
            Schema::create('customer_reminder_outbox', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('contract_type', 30);
                $table->unsignedBigInteger('contract_id');
                $table->unsignedBigInteger('installment_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('channel', 30)->default('call_task');
                $table->string('stage', 50);
                $table->string('recipient_phone', 30);
                $table->string('recipient_name', 191)->nullable();
                $table->text('message_content');
                $table->string('status', 30)->default('pending');
                $table->dateTime('scheduled_at');
                $table->dateTime('sent_at')->nullable();
                $table->string('idempotency_key', 191)->unique();
                $table->text('provider_response')->nullable();
                $table->unsignedInteger('retry_count')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_scan_creates_idempotent_reminders_without_duplicates()
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $customer = Customer::create([
            'name' => 'Nguyễn Văn Test',
            'phone' => '0988776655',
        ]);
        $store = Store::create([
            'store_name' => 'Cơ sở 1',
        ]);
        $vehicle = Vehicle::create([
            'name' => 'Honda Vision',
            'license' => '29A1-99999',
            'store_id' => $store->id,
        ]);

        $contract = LeaseContract::create([
            'contract_code' => 'HĐTSH-TEST-001',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'store_id' => $store->id,
            'start_date' => $today,
            'total_amount' => 12000000,
            'deposit_amount' => 0,
            'installment_count' => 6,
            'period_amount' => 2000000,
            'total_paid' => 0,
            'remaining_debt' => 12000000,
            'status' => 'active',
        ]);

        // Installment due today
        $inst = LeaseInstallment::create([
            'lease_contract_id' => $contract->id,
            'period_number' => 1,
            'due_date' => $today,
            'amount_due' => 2000000,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);

        // First scan
        $res1 = $this->reminderService->scanDueAndOverdueItems();
        $this->assertGreaterThanOrEqual(1, $res1['created']);

        $outbox = CustomerReminderOutbox::where('contract_id', $contract->id)->first();
        $this->assertNotNull($outbox);
        $this->assertEquals('due_today', $outbox->stage);
        $this->assertEquals('0988776655', $outbox->recipient_phone);
        $this->assertEquals('pending', $outbox->status);

        // Second scan immediately: MUST be skipped via idempotency key
        $res2 = $this->reminderService->scanDueAndOverdueItems();
        $this->assertEquals(0, $res2['created']);
        $this->assertGreaterThanOrEqual(1, $res2['skipped']);

        // Only 1 record exists in outbox
        $count = CustomerReminderOutbox::where('contract_id', $contract->id)->count();
        $this->assertEquals(1, $count);
    }

    public function testDailyContactNoteIsVisibleOnlyInTheOriginBranch()
    {
        require_once __DIR__ . '/../../database/migrations/2026_09_22_000004_create_reminder_contact_logs.php';
        (new \CreateReminderContactLogs())->up();

        $store = Store::create(['store_name' => 'CS 1']);
        $anotherStore = Store::create(['store_name' => 'CS 2']);
        $order = Order::create(['store_id' => $store->id, 'order_status' => 'renting']);
        $reminder = CustomerReminderOutbox::create([
            'contract_type' => 'rental_order', 'contract_id' => $order->id,
            'recipient_phone' => '0988776655', 'stage' => 'overdue_1_5d',
            'message_content' => 'Khách quá hạn', 'status' => 'pending',
            'scheduled_at' => Carbon::now(), 'idempotency_key' => 'rental-test-contact-1',
        ]);
        $staff = User::create(['name' => 'Nhân viên CS 1', 'role_id' => 2, 'store_id' => $store->id]);
        $other = User::create(['name' => 'Nhân viên CS 2', 'role_id' => 2, 'store_id' => $anotherStore->id]);

        $this->reminderService->recordContact($reminder->id, 'Đã gọi, khách hẹn chiều nay', $staff);
        $item = $this->reminderService->getStaffActionList([], $staff)['data'][0];
        $this->assertTrue($item['contacted_today']);
        $this->assertSame('Đã gọi, khách hẹn chiều nay', $item['last_contact_note']);
        $this->assertSame(0, $this->reminderService->getStaffActionList([], $other)['total']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->reminderService->recordContact($reminder->id, 'Không thuộc cơ sở này', $other);
    }

    public function testRentalRemindersUseActualVehicleReturnDateAndSkipOrdersWithoutOne()
    {
        $store = Store::create(['store_name' => 'CS 1']);
        $customer = Customer::create(['name' => 'Khách thuê', 'phone' => '0912345678']);
        $vehicle = Vehicle::create(['name' => 'Xe thuê', 'license' => '29A-TEST', 'store_id' => $store->id]);
        Order::create(['store_id' => $store->id, 'customer_id' => $customer->id, 'order_status' => 'renting']);
        $order = Order::create([
            'store_id' => $store->id, 'customer_id' => $customer->id,
            'contract_number' => 'HD-2026-001', 'order_status' => 'renting',
        ]);
        OrderVehicleDetail::create([
            'order_id' => $order->id, 'vehicle_id' => $vehicle->id,
            'rent_at' => Carbon::now('Asia/Ho_Chi_Minh')->subDay(),
            'return_at' => Carbon::now('Asia/Ho_Chi_Minh')->subDay(),
        ]);

        $result = $this->reminderService->scanDueAndOverdueItems();
        $this->assertSame(1, $result['created']);
        $reminder = CustomerReminderOutbox::firstOrFail();
        $this->assertSame($order->id, (int) $reminder->contract_id);
        $this->assertSame('overdue_1_5d', $reminder->stage);
        $this->assertStringContainsString('HD-2026-001', $reminder->message_content);
    }

    public function test_outbox_never_claims_delivery_without_provider()
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh');
        $outbox = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => 999,
            'installment_id' => 888,
            'customer_id' => 777,
            'channel' => 'call_task',
            'stage' => 'due_today',
            'recipient_phone' => '0912345678',
            'recipient_name' => 'Trần Văn Safe',
            'message_content' => 'Nhắc đóng kỳ góp...',
            'status' => 'pending',
            'scheduled_at' => $today->copy()->subMinute(),
            'idempotency_key' => 'lease_999_888_due_today_call_task',
        ]);

        $result = $this->reminderService->processOutbox(50, true);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals('sandbox_dry_run', $result['mode']);

        $outbox->refresh();
        $this->assertEquals('pending', $outbox->status);
        $this->assertNull($outbox->sent_at);
        $this->assertNull($outbox->provider_response);
        $live = $this->reminderService->processOutbox(50, false);
        $this->assertEquals(0, $live['sent']);
        $this->assertEquals(1, $live['failed']);
        $this->assertEquals('pending', $outbox->fresh()->status);
    }

    public function test_gps_service_marks_external_dependency_blocked()
    {
        $overview = $this->gpsService->getFleetOverview();
        $this->assertEquals('BLOCKED_PENDING_EXTERNAL_PROVIDER_CREDENTIALS', $overview['integration_status']);
        $this->assertStringContainsString('Chưa cấu hình nhà cung cấp GPS thực tế', $overview['note']);

        // Normalization checks
        $provider = new MockGpsProvider();
        $this->assertEquals('never_connected', $provider->normalizeStatus(null, null));
        $this->assertEquals('online', $provider->normalizeStatus(null, Carbon::now()->toDateTimeString()));
        $this->assertEquals('stale_offline', $provider->normalizeStatus(null, Carbon::now()->subHours(5)->toDateTimeString()));
        $this->assertEquals('moving', $provider->normalizeStatus('moving', Carbon::now()->toDateTimeString()));
        $this->assertEquals('stopped', $provider->normalizeStatus('stopped', Carbon::now()->toDateTimeString()));
    }

    public function test_today_filter_in_order_repository()
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh');

        $orderToday = Order::create([
            'contract_number' => 'HĐ-TODAY-01',
            'created_at' => $today,
        ]);
        $orderYesterday = Order::create([
            'contract_number' => 'HĐ-YESTERDAY-01',
            'created_at' => $today->copy()->subDays(2),
        ]);

        $repo = app(OrderRepositoryEloquent::class);

        $query = Order::query();
        $filtered = $repo->getOrderByParams($query, ['today_filter' => 'created_today']);

        $results = $filtered->pluck('contract_number')->toArray();
        $this->assertContains('HĐ-TODAY-01', $results);
        $this->assertNotContains('HĐ-YESTERDAY-01', $results);
    }
}
