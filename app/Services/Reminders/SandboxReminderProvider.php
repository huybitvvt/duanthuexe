<?php

namespace App\Services\Reminders;

use App\Contracts\ReminderProviderInterface;
use App\Models\CustomerReminderOutbox;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SandboxReminderProvider implements ReminderProviderInterface
{
    protected $secretKey;
    protected $whitelist;

    public function __construct(?string $secretKey = null, array $whitelist = [])
    {
        $this->secretKey = $secretKey ?: (env('REMINDER_WEBHOOK_SECRET') ?: 'himoto-sandbox-secret-2026');
        $this->whitelist = $whitelist ?: array_filter(explode(',', (string) env('REMINDER_WHITELIST_PHONES', '')));
    }

    /**
     * Send reminder through simulated provider sandbox.
     */
    public function send(CustomerReminderOutbox $item, array $options = []): array
    {
        // Whitelist check if configured
        if (!empty($this->whitelist) && !in_array($item->recipient_phone, $this->whitelist, true)) {
            return [
                'success' => false,
                'provider_message_id' => null,
                'http_status' => 403,
                'error' => 'Phone number ' . $item->recipient_phone . ' is not in sandbox whitelist.',
                'retryable' => false,
            ];
        }

        // Test hook to simulate transient 503 or 429 error
        if (isset($options['force_http_status'])) {
            $status = (int) $options['force_http_status'];
            return [
                'success' => $status >= 200 && $status < 300,
                'provider_message_id' => $status >= 200 && $status < 300 ? ('MSG-SB-' . Str::upper(Str::random(10))) : null,
                'http_status' => $status,
                'error' => $status >= 200 && $status < 300 ? null : "Simulated HTTP error {$status}",
                'retryable' => in_array($status, [429, 500, 502, 503, 504], true),
            ];
        }

        $msgId = 'MSG-SB-' . Str::upper(Str::random(12));

        return [
            'success' => true,
            'provider_message_id' => $msgId,
            'http_status' => 200,
            'error' => null,
            'retryable' => false,
            'accepted_at' => Carbon::now('Asia/Ho_Chi_Minh')->toDateTimeString(),
        ];
    }

    /**
     * Query delivery status from provider.
     */
    public function queryStatus(string $providerMessageId): array
    {
        return [
            'status' => 'delivered',
            'delivered_at' => Carbon::now('Asia/Ho_Chi_Minh')->toDateTimeString(),
            'raw' => [
                'provider_message_id' => $providerMessageId,
                'status_code' => 'DELIVRD',
            ],
        ];
    }

    /**
     * Verify incoming webhook authenticity with HMAC-SHA256 signature and replay prevention.
     */
    public function verifyWebhook(array $payload, array $headers): bool
    {
        $signature = $headers['x-provider-signature'] ?? ($headers['X-Provider-Signature'] ?? null);
        $timestamp = $headers['x-provider-timestamp'] ?? ($headers['X-Provider-Timestamp'] ?? null);

        if (!$signature || !$timestamp) {
            return false;
        }

        // Prevent replay attacks (valid within 5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', (string)$timestamp . '.' . json_encode($payload), $this->secretKey);
        return hash_equals($expected, (string)$signature);
    }

    /**
     * Helper to sign a test webhook payload.
     */
    public function generateWebhookSignature(array $payload, int $timestamp): string
    {
        return hash_hmac('sha256', (string)$timestamp . '.' . json_encode($payload), $this->secretKey);
    }
}
