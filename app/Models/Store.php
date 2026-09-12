<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'store_name',
        'store_phone',
        'store_address',
        'user_id',
        'status'
    ];
    public function banks()
    {
        return $this->hasMany(Bank::class);
    }
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'store_id', 'id');
    }
}
