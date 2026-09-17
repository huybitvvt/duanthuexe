<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleOwnership extends Model
{
    use \App\Traits\HandlesPostgresDates;

    protected $table = 'vehicle_ownerships';

    protected $fillable = [
        'vehicle_id',
        'customer_id',
        'lease_contract_id',
        'ownership_request_id',
        'transferred_at',
        'certificate_number',
        'notes',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Entities\Customer::class, 'customer_id');
    }

    public function contract()
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id');
    }

    public function ownershipRequest()
    {
        return $this->belongsTo(LeaseOwnershipRequest::class, 'ownership_request_id');
    }
}
