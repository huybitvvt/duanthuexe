<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class MaintenanceSchedule extends Model
{
    
    protected $dates = [
           'next_time_auto','next_time_manual'
    ];
    protected $fillable = [
        'vehicle_id',
        'next_time_auto',
        'next_time_manual',
        "maintenance_type_id"
    ];

    public function setNextTimeAutoAttribute($value)
    {
        if(!$value){return;}
        $carbonDate = Carbon::parse($value);

        
        $this->attributes['next_time_auto'] = $carbonDate;
    }

    public function setNextTimeManualAttribute($value)
    {
        if(!$value){return;}
        $carbonDate = Carbon::parse($value);

         
        $this->attributes['next_time_manual'] = $carbonDate;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class,'vehicle_id','id');
    }
    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class,'maintenance_type_id','id');
    }
  
}
