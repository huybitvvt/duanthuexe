<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class OrderVehicleDetail.
 *
 * @package namespace App\Entities;
 */
class OrderVehicleDetail extends Model implements Transformable
{
    use TransformableTrait, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['vehicle_id', 'order_id', 'price_id', 'borrow_hats', 'rent_at', 'return_at', 'total_money', 'status', 'type', 'handler_price','substitute_unit_price' ,'completed_at', 'minute_out_date', 'money_out_date', 'odometer_before', 'odometer_after', 'hiring_fee'];

	protected $dates   = [
		'completed_at',  
	];
	
	public function setCompletedAtAttribute($value)
	{
		if ( !$value ) {
			return;
		}
		$this->attributes['completed_at'] =  date('Y-m-d H:i:s', strtotime($value));
	}
  
    public function orderItemFees(): HasMany
    {
        return $this->hasMany(Transaction::class, 'order_item_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }
}
