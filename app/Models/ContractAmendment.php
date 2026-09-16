<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
