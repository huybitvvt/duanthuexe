<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Bank extends Model
{
    //
    protected $fillable = ['bank_name', 'account_number', 'account_type', 'owner_name', 'store_id', 'opening_balance'];
    // public function store()
    // {
    //     return $this->belongsTo(Store::class);
    // }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'bank_id', 'id');
    }

}
