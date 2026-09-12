<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cash extends Model
{
    protected $table = 'cash';
    protected $fillable = [  'store_id', 'opening_balance'];
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cash_id', 'id');
    }
}
