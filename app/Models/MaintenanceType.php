<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class MaintenanceType extends Model
{
    
    protected $fillable = ['name', 'note'];
   
    public function maintenanceLogs() : HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'maintenance_type_id','id');
    }
    public function setValueAttribute($value){
        $this->attributes['value'] = (int) $value;
    }

}
