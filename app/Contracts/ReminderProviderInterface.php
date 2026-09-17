<?php

namespace App\Contracts;

use App\Models\CustomerReminderOutbox;

interface ReminderProviderInterface
{
    /**
     * Send reminder to provider.
     *
     * @param CustomerReminderOutbox $item
     * @param array $options
     * @return array [
     *   'success' => bool,
     *   'provider_message_id' => ?string,
     *   'http_status' => int,
     *   'error' => ?string,
     *   'retryable' => bool,
     * ]
     */
    public function send(CustomerReminderOutbox $item, array $options = []): array;

    /**
     * Query delivery status by provider message ID.
     *
     * @param string $providerMessageId
     * @return array [
     *   'status' => string, // delivered, failed, pending
     *   'delivered_at' => ?string,
     *   'raw' => array,
     * ]
     */
    public function queryStatus(string $providerMessageId): array;

    /**
     * Verify incoming webhook authenticity and signature.
     *
     * @param array $payload
     * @param array $headers
     * @return bool
     */
    public function verifyWebhook(array $payload, array $headers): bool;
}
