<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GpsDevice extends Model
{
    protected $table = 'gps_devices';

    protected $fillable = [
        'vehicle_id',
        'provider',
        'external_device_id',
        'imei',
        'sim_phone',
        'mapping_status',
        'last_sync_at',
        'device_metadata',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'device_metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(GpsPosition::class, 'gps_device_id', 'id');
    }

    public function latestPosition(): HasOne
    {
        return $this->hasOne(GpsPosition::class, 'gps_device_id', 'id')->latest('provider_recorded_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(GpsAlert::class, 'gps_device_id', 'id');
    }

    public function recoveryActions(): HasMany
    {
        return $this->hasMany(GpsRecoveryAction::class, 'gps_device_id', 'id');
    }
}
