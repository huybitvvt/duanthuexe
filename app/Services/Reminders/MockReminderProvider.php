<?php

namespace App\Services\Reminders;

use App\Contracts\ReminderProviderInterface;
use App\Models\CustomerReminderOutbox;

class MockReminderProvider implements ReminderProviderInterface
{
    public $sentItems = [];
    public $shouldFail = false;
    public $failStatus = 500;
    public $failMessage = 'Mock failure';

    public function send(CustomerReminderOutbox $item, array $options = []): array
    {
        if ($this->shouldFail) {
            return [
                'success' => false,
                'provider_message_id' => null,
                'http_status' => $this->failStatus,
                'error' => $this->failMessage,
                'retryable' => in_array($this->failStatus, [429, 500, 503], true),
            ];
        }

        $msgId = 'MOCK-MSG-' . count($this->sentItems) . '-' . $item->id;
        $this->sentItems[] = [
            'item_id' => $item->id,
            'phone' => $item->recipient_phone,
            'message' => $item->message_content,
            'provider_message_id' => $msgId,
        ];

        return [
            'success' => true,
            'provider_message_id' => $msgId,
            'http_status' => 200,
            'error' => null,
            'retryable' => false,
        ];
    }

    public function queryStatus(string $providerMessageId): array
    {
        return [
            'status' => 'delivered',
            'delivered_at' => now()->toDateTimeString(),
            'raw' => ['provider_message_id' => $providerMessageId],
        ];
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        return true;
    }
}
