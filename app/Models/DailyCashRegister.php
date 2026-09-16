<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCashRegister extends Model
{
    protected $table = 'daily_cash_registers';

    protected $fillable = [
        'store_id',
        'register_date',
        'opening_balance',
        'total_orders_count',
        'deposit_cash',
        'rental_cash',
        'renewal_cash',
        'refund_deposit_cash',
        'penalty_cash',
        'other_income_cash',
        'other_expense_cash',
        'deposit_bank_personal',
        'rental_bank_personal',
        'renewal_bank_personal',
        'refund_deposit_bank_personal',
        'penalty_bank_personal',
        'other_income_bank_personal',
        'other_expense_bank_personal',
        'deposit_bank_company',
        'rental_bank_company',
        'renewal_bank_company',
        'refund_deposit_bank_company',
        'penalty_bank_company',
        'other_income_bank_company',
        'other_expense_bank_company',
        'system_cash_balance',
        'actual_cash_counted',
        'cash_difference',
        'difference_reason',
        'status',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'register_date' => 'date:Y-m-d',
        'opening_balance' => 'float',
        'total_orders_count' => 'integer',
        'deposit_cash' => 'float',
        'rental_cash' => 'float',
        'renewal_cash' => 'float',
        'refund_deposit_cash' => 'float',
        'penalty_cash' => 'float',
        'other_income_cash' => 'float',
        'other_expense_cash' => 'float',
        'deposit_bank_personal' => 'float',
        'rental_bank_personal' => 'float',
        'renewal_bank_personal' => 'float',
        'refund_deposit_bank_personal' => 'float',
        'penalty_bank_personal' => 'float',
        'other_income_bank_personal' => 'float',
        'other_expense_bank_personal' => 'float',
        'deposit_bank_company' => 'float',
        'rental_bank_company' => 'float',
        'renewal_bank_company' => 'float',
        'refund_deposit_bank_company' => 'float',
        'penalty_bank_company' => 'float',
        'other_income_bank_company' => 'float',
        'other_expense_bank_company' => 'float',
        'system_cash_balance' => 'float',
        'actual_cash_counted' => 'float',
        'cash_difference' => 'float',
        'closed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
