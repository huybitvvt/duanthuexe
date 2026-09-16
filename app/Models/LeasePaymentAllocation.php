<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeasePaymentAllocation extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const STATUS_ACTIVE = 'active';
    const STATUS_REVERSED = 'reversed';
    const STATUS_DISCOUNT = 'discount';

    protected $fillable = [
        'lease_contract_id',
        'installment_id',
        'transaction_id',
        'amount',
        'payment_date',
        'notes',
        'status',
        'reversal_transaction_id',
        'reversal_reason',
        'reversed_at',
        'reversed_by',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id', 'id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LeaseInstallment::class, 'installment_id', 'id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function scopeEffectivePayments($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', self::STATUS_ACTIVE);
        });
    }

    public function scopeDiscountAdjustments($query)
    {
        return $query->where('status', self::STATUS_DISCOUNT);
    }
}
