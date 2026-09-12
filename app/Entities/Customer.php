<?php

namespace App\Entities;
use \App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * Class Customer.
 *
 * @package namespace App\Entities;
 */
class Customer extends Model implements Transformable
{
    use \App\Traits\HandlesPostgresDates;
    use TransformableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'phone', 'address', 'id_card', 'status', 'warning'];
    const STATUS_ACTIVE = 1;
    const STATUS_NOT_ACTIVE = 0;
    const STATUS_WARNING = 2;
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }
}
