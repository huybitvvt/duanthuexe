<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPosition extends Model
{
    protected $table = 'gps_positions';

    protected $fillable = [
        'gps_device_id',
        'latitude',
        'longitude',
        'speed',
        'ignition',
        'heading',
        'provider_recorded_at',
        'received_at',
        'normalized_status',
        'raw_payload',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'ignition' => 'boolean',
        'heading' => 'float',
        'provider_recorded_at' => 'datetime',
        'received_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(GpsDevice::class, 'gps_device_id', 'id');
    }
}
