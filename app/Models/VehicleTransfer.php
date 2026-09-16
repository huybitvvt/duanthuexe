<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleTransfer extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const STATUS_DRAFT = 'draft';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const TYPE_STORE_TO_STORE = 'store_to_store';
    const TYPE_RETURN_DIFFERENT_STORE = 'return_different_store';
    const TYPE_VEHICLE_EXCHANGE = 'vehicle_exchange';

    protected $fillable = [
        'transfer_code',
        'type',
        'from_store_id',
        'to_store_id',
        'status',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'order_id',
        'reason',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id', 'id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VehicleTransferItem::class, 'transfer_id', 'id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by', 'id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
