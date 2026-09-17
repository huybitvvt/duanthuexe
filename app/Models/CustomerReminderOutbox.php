<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReminderOutbox extends Model
{
    protected $table = 'customer_reminder_outbox';

    protected $fillable = [
        'contract_type',
        'contract_id',
        'installment_id',
        'customer_id',
        'channel',
        'provider',
        'provider_message_id',
        'stage',
        'template_code',
        'recipient_phone',
        'recipient_name',
        'message_content',
        'status',
        'scheduled_at',
        'attempted_at',
        'next_attempt_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'locked_at',
        'locked_by',
        'last_http_status',
        'idempotency_key',
        'provider_response',
        'retry_count',
        'error_message',
        'consent_source',
        'consent_captured_at',
        'cancel_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'attempted_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'locked_at' => 'datetime',
        'retry_count' => 'integer',
        'last_http_status' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LeaseInstallment::class, 'installment_id');
    }

    public function deliveryEvents()
    {
        return $this->hasMany(ReminderDeliveryEvent::class, 'outbox_id', 'id');
    }
}
