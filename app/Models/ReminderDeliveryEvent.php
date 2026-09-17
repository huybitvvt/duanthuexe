<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDeliveryEvent extends Model
{
    public $timestamps = false;
    protected $table = 'reminder_delivery_events';

    protected $fillable = [
        'outbox_id',
        'provider',
        'provider_message_id',
        'event_type',
        'http_status',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(CustomerReminderOutbox::class, 'outbox_id', 'id');
    }
}
