<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsRecoveryAction extends Model
{
    protected $table = 'gps_recovery_actions';

    protected $fillable = [
        'vehicle_id',
        'gps_device_id',
        'assigned_to',
        'recovery_plan',
        'deadline',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(GpsDevice::class, 'gps_device_id', 'id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
