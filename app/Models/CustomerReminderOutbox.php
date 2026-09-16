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
        'stage',
        'recipient_phone',
        'recipient_name',
        'message_content',
        'status',
        'scheduled_at',
        'sent_at',
        'idempotency_key',
        'provider_response',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LeaseInstallment::class, 'installment_id');
    }
}
