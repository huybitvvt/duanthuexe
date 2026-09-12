<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class MaintenanceLog extends Model
{
    use \App\Traits\HandlesPostgresDates;
    protected $table = 'maintenance_log';
    protected $fillable = ['vehicle_id', 'maintenance_at', 'note','maintenance_type_id'];
   

    public function vehicles(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }
    public function maintenanceType() : BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id','id');
    }

}
