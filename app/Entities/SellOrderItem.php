<?php

namespace App\Entities;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class SellOrderItem.
 *
 * @package namespace App\Entities;
 */
class SellOrderItem extends Model implements Transformable
{
    use \App\Traits\HandlesPostgresDates;
    use TransformableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'price',
        'desc',
        'vehicle_id',
        'vehicle_cost_price'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

}
