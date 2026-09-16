<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeasePaymentAllocation extends Model
{
    use \App\Traits\HandlesPostgresDates;

    protected $fillable = [
        'lease_contract_id',
        'installment_id',
        'transaction_id',
        'amount',
        'payment_date',
        'notes',
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
}
