<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTransferItem extends Model
{
    use \App\Traits\HandlesPostgresDates;

    protected $fillable = [
        'transfer_id',
        'vehicle_id',
        'source_status',
        'target_status',
        'odometer_out',
        'odometer_in',
        'condition_notes',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(VehicleTransfer::class, 'transfer_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }
}
