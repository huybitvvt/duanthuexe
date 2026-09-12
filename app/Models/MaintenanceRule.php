<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MaintenanceRule extends Model
{
    
    protected $fillable = [
       'maintenance_type_id',
        'value',
        "next_time"
    ];
    protected $dates = [
        'next_time'
    ];
    public function vehicles(): HasOne
    {
        return $this->hasOne(Vehicle::class,'vehicle_id','id');
    }
    public function setNextTimeAttribute($value)
    {
        if(!$value){return;}
        $carbonDate = Carbon::parse($value);

         
        $this->attributes['next_time'] = $carbonDate;
    }
}
