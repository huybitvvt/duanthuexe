<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractAmendment extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const TYPE_VEHICLE_EXCHANGE = 'vehicle_exchange';
    const TYPE_PRICE_ADJUSTMENT = 'price_adjustment';
    const TYPE_EXTENSION = 'extension';

    protected $fillable = [
        'order_id',
        'amendment_code',
        'amendment_type',
        'old_vehicle_id',
        'new_vehicle_id',
        'effective_at',
        'price_difference',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'price_difference' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function oldVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'old_vehicle_id', 'id');
    }

    public function newVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'new_vehicle_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function oldVehicleEvent(): HasOne
    {
        return $this->hasOne(VehicleLocationEvent::class, 'ref_id', 'id')
            ->where('ref_type', 'contract_amendments')
            ->where('event_type', VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_OUT);
    }

    public function newVehicleEvent(): HasOne
    {
        return $this->hasOne(VehicleLocationEvent::class, 'ref_id', 'id')
            ->where('ref_type', 'contract_amendments')
            ->where('event_type', VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_IN);
    }
}
