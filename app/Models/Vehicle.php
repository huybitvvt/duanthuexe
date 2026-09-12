<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use \App\Traits\HandlesPostgresDates;
    const STATUS_READY = 'ready';
    const STATUS_PENDING = 'pending';
    const STATUS_USING = 'using';
    const STATUS_REPAIRING = 'repairing';
    const STATUS_SOLD = 'sold';
    const STATUS_BAD_DEBT = 'bad_debt';
    const STATUS_BROKEN = 'broken';

    const TYPE_XESO = 'xeso';
    const TYPE_XEGA = 'xega';
    const TYPE_XECON = 'xecon';
    const TYPE_XE_SH = 'xesh';

    protected $fillable = [
        'name',
        'brand',
        'type',
        'year',
        'store_id',
        'license',
        'chassis',
        'engine',
        'status',
        'cost_price',
        'sale_price',
        'price_range',
        'created_by',
        'color',
        'type_of_service_id',
        'price_min',
        'price_max',
		'odometer'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_vehicle_details', 'vehicle_id', 'order_id');
    }
   
    public function orderVehicleDetails(): HasMany
    {
        return $this->hasMany(OrderVehicleDetail::class, 'vehicle_id', 'id');
    }
    public function maintenanceLog(): HasMany
    {
        return $this->HasMany(MaintenanceLog::class,'vehicle_id','id');
    }
    public function maintenanceVehicle(): HasOne
    {
        return $this->hasOne(MaintenanceVehicle::class,'vehicle_id','id');
    }
    public function maintenanceRules(): HasOne
    {
        return $this->hasOne(MaintenanceRule::class,'vehicle_id','id');
    }
    public function maintenanceSchedule(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class,'vehicle_id','id');
    }

	public function images(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'vehicle_images', 'vehicle_id', 'file_id');
    }
}
