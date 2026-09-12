<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class PriceVehicle extends Model
{
    use \App\Traits\HandlesPostgresDates;
    protected $table = 'pricing';

    protected $fillable = ['type', 'from_year', 'to_year', 'from_date', 'to_date', 'price', 'price_type'];

    protected $casts = [
        'from_year' => 'integer',
        'to_year' => 'integer',
        'from_date' => 'integer',
        'to_date' => 'integer',
    ];
}
