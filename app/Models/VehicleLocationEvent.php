<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLocationEvent extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const EVENT_TRANSFER_DISPATCH = 'transfer_dispatch';
    const EVENT_TRANSFER_RECEIVE = 'transfer_receive';
    const EVENT_TRANSFER_CANCEL = 'transfer_cancel';
    const EVENT_ORDER_RENT_OUT = 'order_rent_out';
    const EVENT_ORDER_RETURN_SAME = 'order_return_same_store';
    const EVENT_ORDER_RETURN_DIFFERENT = 'order_return_different_store';
    const EVENT_VEHICLE_EXCHANGE_IN = 'vehicle_exchange_in';
    const EVENT_VEHICLE_EXCHANGE_OUT = 'vehicle_exchange_out';

    protected $fillable = [
        'vehicle_id',
        'from_store_id',
        'to_store_id',
        'event_type',
        'ref_type',
        'ref_id',
        'odometer',
        'notes',
        'created_by',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id', 'id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
