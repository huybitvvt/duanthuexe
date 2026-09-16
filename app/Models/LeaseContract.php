<?php

namespace App\Models;

use App\Entities\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaseContract extends Model
{
    use \App\Traits\HandlesPostgresDates;
    use SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DEFAULTED = 'defaulted';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'contract_code',
        'customer_id',
        'vehicle_id',
        'store_id',
        'start_date',
        'end_date',
        'total_amount',
        'deposit_amount',
        'installment_count',
        'period_amount',
        'status',
        'discount_amount',
        'settled_at',
        'assigned_user_id',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'float',
        'deposit_amount' => 'float',
        'period_amount' => 'float',
        'discount_amount' => 'float',
        'settled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LeaseInstallment::class, 'lease_contract_id', 'id')->orderBy('period_number', 'asc');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeasePaymentAllocation::class, 'lease_contract_id', 'id')->orderBy('id', 'desc');
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(LeasePaymentAllocation::class, 'lease_contract_id', 'id')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', LeasePaymentAllocation::STATUS_ACTIVE);
            })
            ->orderBy('id', 'desc');
    }

    public function debtNotes(): HasMany
    {
        return $this->hasMany(DebtNote::class, 'lease_contract_id', 'id')->orderBy('id', 'desc');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }
}
