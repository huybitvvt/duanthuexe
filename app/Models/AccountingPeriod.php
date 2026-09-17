<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $table = 'accounting_periods';

    protected $fillable = [
        'fiscal_year', 'period_month', 'start_date', 'end_date', 'status',
        'closed_at', 'closed_by', 'reopened_at', 'reopened_by', 'notes',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'period_month' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'closed_by' => 'integer',
        'reopened_by' => 'integer',
    ];

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedByUser()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
