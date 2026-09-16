<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use \App\Traits\HandlesPostgresDates;
    const KIND_PHYSICAL = 'physical';
    const KIND_LEASE_TO_OWN = 'lease_to_own';

    protected $fillable = [
        'store_name',
        'store_phone',
        'store_address',
        'user_id',
        'status',
        'kind',
        'code'
    ];
    public function banks()
    {
        return $this->hasMany(Bank::class);
    }
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'store_id', 'id');
    }
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'store_id', 'id');
    }
    public function currentVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'current_store_id', 'id');
    }
}
