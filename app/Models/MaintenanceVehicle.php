<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class MaintenanceVehicle extends Model
{
    protected $table = 'maintenance_vehicle';
    protected $dates = [
        'last_time',  'next_time'
    ];
    protected $fillable = [
        'vehicle_id',
        'generic_interval',
        'group_interval',
        'individual_interval',
        'days_until_due',
        'last_time',
        "next_time",
        "maintenance_type_id"
    ];

    public function setLastTimeAttribute($value)
    {
        if(!$value){return;}
        $carbonDate = Carbon::parse($value);

        
        $this->attributes['last_time'] = $carbonDate;
    }

    public function setNextTimeAttribute($value)
    {
        if(!$value){return;}
        $carbonDate = Carbon::parse($value);

         
        $this->attributes['next_time'] = $carbonDate;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class,'vehicle_id','id');
    }
  
}
