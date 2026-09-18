<?php

namespace App\Models;

use App\Entities\AddOnOrder;
use App\Entities\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

class Order extends Model implements Transformable
{
    use \App\Traits\HandlesPostgresDates;
    use TransformableTrait, SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'contract_snapshot' => 'array',
        'contract_signed_on' => 'date',
        'contract_authorization_date' => 'date',
        'contract_issued_at' => 'datetime',
    ];

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contract_responsible_user_id', 'id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'order_vehicle_details', 'order_id', 'vehicle_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderVehicleDetail::class, 'order_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'order_id', 'id');
    }


    /**
     * @return HasMany
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'order_id', 'id');
    }

    public function contractAmendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'order_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function addOnOrders()
    {
        return $this->hasMany(AddOnOrder::class, 'order_id', 'id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'order_id', 'id');
    }

}
