<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractNumberCounter extends Model
{
    protected $table = 'contract_number_counters';
    protected $primaryKey = 'number_date';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'number_date',
        'last_number',
    ];
}
