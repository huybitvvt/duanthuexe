<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsAlert extends Model
{
    protected $table = 'gps_alerts';

    protected $fillable = [
        'gps_device_id',
        'vehicle_id',
        'alert_type',
        'severity',
        'status',
        'opened_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(GpsDevice::class, 'gps_device_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'id');
    }
}
