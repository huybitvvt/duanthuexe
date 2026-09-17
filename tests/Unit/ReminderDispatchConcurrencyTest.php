<?php

namespace Tests\Unit;

use App\Http\Services\CustomerReminderService;
use App\Models\CustomerReminderOutbox;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\ReminderDeliveryEvent;
use App\Models\User;
use App\Services\Reminders\MockReminderProvider;
use App\Services\Reminders\SandboxReminderProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReminderDispatchConcurrencyTest extends TestCase
{
    protected $service;
    protected $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->mockProvider = new MockReminderProvider();
        $this->service = new CustomerReminderService($this->mockProvider);
    }

    public function test_concurrent_workers_claim_distinct_records_without_collision(): void
    {
        // Seed 4 pending items
        for ($i = 1; $i <= 4; $i++) {
            CustomerReminderOutbox::create([
                'contract_type' => 'lease',
                'contract_id' => $i,
                'recipient_phone' => '098765432' . $i,
                'recipient_name' => 'Khách ' . $i,
                'message_content' => 'Nhắc nợ ' . $i,
                'status' => 'pending',
                'idempotency_key' => 'idemp-worker-' . $i,
            ]);
        }

        // Worker 1 claims limit 2
        $res1 = $this->service->claimAndDispatchBatch(2, 'worker-A', $this->mockProvider, ['ignore_quiet_hours' => true, 'allow_sandbox' => true]);
        $this->assertEquals(2, $res1['claimed']);
        $this->assertEquals(2, $res1['dispatched']);

        // Worker 2 claims limit 2 concurrently
        $res2 = $this->service->claimAndDispatchBatch(2, 'worker-B', $this->mockProvider, ['ignore_quiet_hours' => true, 'allow_sandbox' => true]);
        $this->assertEquals(2, $res2['claimed']);
        $this->assertEquals(2, $res2['dispatched']);

        // Worker 3 attempts to claim: should find 0 pending items
        $res3 = $this->service->claimAndDispatchBatch(2, 'worker-C', $this->mockProvider, ['ignore_quiet_hours' => true, 'allow_sandbox' => true]);
        $this->assertEquals(0, $res3['claimed']);

        // Total dispatched items across both workers must be 4, with exactly 4 distinct sent records
        $this->assertCount(4, $this->mockProvider->sentItems);
        $this->assertEquals(4, CustomerReminderOutbox::where('status', 'sent')->count());
    }

    public function test_live_dispatch_is_locked_without_explicit_live_provider_configuration(): void
    {
        putenv('REMINDER_LIVE_ENABLED=false');

        $this->expectException(ValidationException::class);
        $this->service->claimAndDispatchBatch(1, 'worker-live-guard', $this->mockProvider, [
            'ignore_quiet_hours' => true,
        ]);
    }

    public function test_staff_action_list_is_restricted_to_assigned_store(): void
    {
        $storeOneContract = LeaseContract::create([
            'contract_code' => 'STORE-1', 'customer_id' => 1, 'vehicle_id' => 1,
            'store_id' => 1, 'total_amount' => 1000000, 'status' => 'active',
        ]);
        $storeTwoContract = LeaseContract::create([
            'contract_code' => 'STORE-2', 'customer_id' => 2, 'vehicle_id' => 2,
            'store_id' => 2, 'total_amount' => 1000000, 'status' => 'active',
        ]);
        CustomerReminderOutbox::create([
            'contract_type' => 'lease', 'contract_id' => $storeOneContract->id,
            'recipient_phone' => '0900000001', 'status' => 'pending', 'idempotency_key' => 'scope-1',
        ]);
        CustomerReminderOutbox::create([
            'contract_type' => 'lease', 'contract_id' => $storeTwoContract->id,
            'recipient_phone' => '0900000002', 'status' => 'pending', 'idempotency_key' => 'scope-2',
        ]);

        $staff = new User(['role' => 'nhan-vien', 'store_id' => 1]);
        $result = $this->service->getStaffActionList(['per_page' => 20], $staff);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('0900000001', $result['data'][0]['recipient_phone']);
    }

    public function test_installment_paid_before_dispatch_is_skipped(): void
    {
        $contract = LeaseContract::create([
            'contract_code' => 'HD-SKIP-01',
            'customer_id' => 1,
            'vehicle_id' => 1,
            'store_id' => 1,
            'total_amount' => 5000000,
            'status' => 'active',
        ]);

        $installment = LeaseInstallment::create([
            'lease_contract_id' => $contract->id,
            'period_number' => 1,
            'amount_due' => 1000000,
            'amount_paid' => 1000000,
            'status' => LeaseInstallment::STATUS_PAID, // Paid!
        ]);

        $item = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => $contract->id,
            'installment_id' => $installment->id,
            'recipient_phone' => '0987654321',
            'recipient_name' => 'Khách Đã Trả',
            'message_content' => 'Nhắc nợ',
            'status' => 'pending',
            'idempotency_key' => 'idemp-paid-skip',
        ]);

        $res = $this->service->claimAndDispatchBatch(10, 'worker-1', $this->mockProvider, ['ignore_quiet_hours' => true, 'allow_sandbox' => true]);
        $this->assertEquals(1, $res['claimed']);
        $this->assertEquals(0, $res['dispatched']);
        $this->assertEquals(1, $res['skipped']);

        $item->refresh();
        $this->assertEquals('skipped', $item->status);
        $this->assertStringContainsString('paid before dispatch', $item->cancel_reason);

        // Provider must NOT have been called
        $this->assertEmpty($this->mockProvider->sentItems);
    }

    public function test_retry_backoff_and_dead_letter_transition(): void
    {
        $sandbox = new SandboxReminderProvider('secret', ['0987654321']);

        $item = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => 1,
            'recipient_phone' => '0987654321',
            'recipient_name' => 'Khách Test Retry',
            'message_content' => 'Nhắc nợ',
            'status' => 'pending',
            'retry_count' => 0,
            'idempotency_key' => 'idemp-retry-fail',
        ]);

        // Attempt 1: Force HTTP 503 (transient error)
        $res1 = $this->service->claimAndDispatchBatch(1, 'worker-retry', $sandbox, [
            'ignore_quiet_hours' => true,
            'allow_sandbox' => true,
            'force_http_status' => 503,
        ]);
        $this->assertEquals(1, $res1['failed']);

        $item->refresh();
        $this->assertEquals('pending', $item->status);
        $this->assertEquals(1, $item->retry_count);
        $this->assertNotNull($item->next_attempt_at);
        $this->assertEquals(503, $item->last_http_status);

        // Attempt 2: Force HTTP 503 again
        // Advance next_attempt_at to now
        $item->update(['next_attempt_at' => Carbon::now('Asia/Ho_Chi_Minh')->subMinute()]);
        $this->service->claimAndDispatchBatch(1, 'worker-retry', $sandbox, [
            'ignore_quiet_hours' => true,
            'allow_sandbox' => true,
            'force_http_status' => 503,
        ]);

        $item->refresh();
        $this->assertEquals(2, $item->retry_count);
        $this->assertEquals('pending', $item->status);

        // Attempt 3: Force HTTP 503 again -> will reach max retries (3)
        $item->update(['next_attempt_at' => Carbon::now('Asia/Ho_Chi_Minh')->subMinute()]);
        $res3 = $this->service->claimAndDispatchBatch(1, 'worker-retry', $sandbox, [
            'ignore_quiet_hours' => true,
            'allow_sandbox' => true,
            'force_http_status' => 503,
        ]);
        $this->assertEquals(1, $res3['dead_letter']);

        $item->refresh();
        $this->assertEquals('dead_letter', $item->status);
        $this->assertNotNull($item->failed_at);
        $this->assertStringContainsString('max retry limit', $item->cancel_reason);
    }

    protected function createTestTables(): void
    {
        Schema::dropIfExists('reminder_delivery_events');
        Schema::create('reminder_delivery_events', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('outbox_id');
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->string('event_type', 50);
            $table->integer('http_status')->nullable();
            $table->text('payload_json')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        Schema::dropIfExists('customer_reminder_outbox');
        Schema::create('customer_reminder_outbox', function ($table) {
            $table->increments('id');
            $table->string('contract_type', 50)->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('installment_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('channel', 50)->default('call_task');
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->string('stage', 50)->nullable();
            $table->string('template_code', 50)->nullable();
            $table->string('recipient_phone', 50)->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->text('message_content')->nullable();
            $table->string('status', 50)->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->string('locked_by', 100)->nullable();
            $table->integer('last_http_status')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('provider_response')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->string('consent_source', 100)->nullable();
            $table->dateTime('consent_captured_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('customers');
        Schema::create('customers', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
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
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('orders');
        Schema::create('orders', function ($table) {
            $table->increments('id');
            $table->integer('store_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('lease_installments');
        Schema::create('lease_installments', function ($table) {
            $table->increments('id');
            $table->integer('lease_contract_id');
            $table->integer('period_number')->nullable();
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });
    }
}
