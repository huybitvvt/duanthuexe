<?php

namespace Tests\Unit;

use App\Contracts\ReminderProviderInterface;
use App\Entities\Customer;
use App\Http\Services\CustomerReminderService;
use App\Models\CustomerReminderOutbox;
use App\Models\ReminderDeliveryEvent;
use App\Services\Reminders\MockReminderProvider;
use App\Services\Reminders\SandboxReminderProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReminderProviderContractTest extends TestCase
{
    protected $service;
    protected $sandboxProvider;
    protected $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->sandboxProvider = new SandboxReminderProvider('test-secret-key-2026', ['0987654321', '0912345678']);
        $this->mockProvider = new MockReminderProvider();
        $this->service = new CustomerReminderService($this->sandboxProvider);
    }

    public function test_providers_implement_interface(): void
    {
        $this->assertInstanceOf(ReminderProviderInterface::class, $this->sandboxProvider);
        $this->assertInstanceOf(ReminderProviderInterface::class, $this->mockProvider);
    }

    public function test_sandbox_provider_send_whitelisted_and_rejected_numbers(): void
    {
        $outboxAllowed = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => 1,
            'recipient_phone' => '0987654321',
            'recipient_name' => 'Khách Whitelist',
            'message_content' => 'Nhắc nợ thử nghiệm',
            'status' => 'pending',
            'idempotency_key' => 'test-idemp-1',
        ]);

        $res = $this->sandboxProvider->send($outboxAllowed);
        $this->assertTrue($res['success']);
        $this->assertNotNull($res['provider_message_id']);
        $this->assertEquals(200, $res['http_status']);

        // Non-whitelisted number
        $outboxBlocked = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => 2,
            'recipient_phone' => '0999999999',
            'recipient_name' => 'Khách Lạ',
            'message_content' => 'Nhắc nợ bị chặn',
            'status' => 'pending',
            'idempotency_key' => 'test-idemp-2',
        ]);

        $resBlocked = $this->sandboxProvider->send($outboxBlocked);
        $this->assertFalse($resBlocked['success']);
        $this->assertEquals(403, $resBlocked['http_status']);
        $this->assertStringContainsString('not in sandbox whitelist', $resBlocked['error']);
    }

    public function test_webhook_signature_verification_and_replay_prevention(): void
    {
        $payload = [
            'provider_message_id' => 'MSG-SB-TEST12345',
            'status' => 'delivered',
        ];

        $now = time();
        $validSignature = $this->sandboxProvider->generateWebhookSignature($payload, $now);

        // Valid signature & fresh timestamp
        $this->assertTrue($this->sandboxProvider->verifyWebhook($payload, [
            'x-provider-signature' => $validSignature,
            'x-provider-timestamp' => (string) $now,
        ]));

        // Invalid signature
        $this->assertFalse($this->sandboxProvider->verifyWebhook($payload, [
            'x-provider-signature' => 'invalid-signature-hash',
            'x-provider-timestamp' => (string) $now,
        ]));

        // Expired timestamp (replay attack: 10 minutes ago)
        $oldTimestamp = $now - 600;
        $oldSignature = $this->sandboxProvider->generateWebhookSignature($payload, $oldTimestamp);
        $this->assertFalse($this->sandboxProvider->verifyWebhook($payload, [
            'x-provider-signature' => $oldSignature,
            'x-provider-timestamp' => (string) $oldTimestamp,
        ]));
    }

    public function test_webhook_processing_updates_outbox_idempotently(): void
    {
        $outbox = CustomerReminderOutbox::create([
            'contract_type' => 'lease',
            'contract_id' => 10,
            'recipient_phone' => '0987654321',
            'recipient_name' => 'Khách Hàng',
            'message_content' => 'Nội dung nhắc nợ',
            'status' => 'sent',
            'provider' => 'SandboxReminderProvider',
            'provider_message_id' => 'MSG-SB-DELIVERY-999',
            'idempotency_key' => 'test-webhook-outbox',
        ]);

        $payload = [
            'provider_message_id' => 'MSG-SB-DELIVERY-999',
            'status' => 'delivered',
        ];

        $now = time();
        $sig = $this->sandboxProvider->generateWebhookSignature($payload, $now);
        $headers = [
            'x-provider-signature' => $sig,
            'x-provider-timestamp' => (string) $now,
        ];

        // 1. Process webhook
        $result = $this->service->handleWebhook('SandboxReminderProvider', $payload, $headers, $this->sandboxProvider);
        $this->assertEquals('updated', $result['status']);
        $this->assertEquals('delivered', $result['current_status']);

        $outbox->refresh();
        $this->assertEquals('delivered', $outbox->status);
        $this->assertNotNull($outbox->delivered_at);

        $events = ReminderDeliveryEvent::where('outbox_id', $outbox->id)->get();
        $this->assertCount(1, $events);
        $this->assertEquals('delivered', $events->first()->event_type);

        // 2. Re-send same webhook (Idempotency check)
        $retryResult = $this->service->handleWebhook('SandboxReminderProvider', $payload, $headers, $this->sandboxProvider);
        $this->assertEquals('already_delivered', $retryResult['status']);

        // Event count must not duplicate
        $this->assertEquals(1, ReminderDeliveryEvent::where('outbox_id', $outbox->id)->count());
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
    }
}
