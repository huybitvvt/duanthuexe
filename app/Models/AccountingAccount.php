<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAccount extends Model
{
    protected $table = 'accounting_accounts';

    protected $fillable = [
        'code', 'name', 'type', 'parent_id', 'normal_balance', 'is_active', 'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(AccountingAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AccountingAccount::class, 'parent_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }
}
