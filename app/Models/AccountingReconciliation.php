<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingReconciliation extends Model
{
    protected $table = 'accounting_reconciliations';

    protected $fillable = [
        'period_id', 'store_id', 'account_type', 'reconciliation_date',
        'book_balance', 'actual_balance', 'difference', 'status',
        'reconciled_by', 'reconciled_at', 'notes',
    ];

    protected $casts = [
        'period_id' => 'integer',
        'store_id' => 'integer',
        'reconciliation_date' => 'date:Y-m-d',
        'book_balance' => 'float',
        'actual_balance' => 'float',
        'difference' => 'float',
        'reconciled_by' => 'integer',
        'reconciled_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function reconciledByUser()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
