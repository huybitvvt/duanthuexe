<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaseInstallment extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'lease_contract_id',
        'period_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
        'paid_at',
        'notes',
    ];

    protected $appends = ['remaining_amount', 'expected_amount', 'paid_amount'];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount_due' => 'float',
        'amount_paid' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id', 'id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeasePaymentAllocation::class, 'installment_id', 'id');
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float)max(0, ($this->amount_due ?? 0) - ($this->amount_paid ?? 0));
    }

    public function getExpectedAmountAttribute(): float
    {
        return (float)($this->amount_due ?? 0);
    }

    public function getPaidAmountAttribute(): float
    {
        return (float)($this->amount_paid ?? 0);
    }
}
