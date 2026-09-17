<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $table = 'journal_lines';

    protected $fillable = [
        'journal_entry_id', 'account_id', 'store_id', 'debit', 'credit',
        'description', 'reference_type', 'reference_id',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'store_id' => 'integer',
        'debit' => 'float',
        'credit' => 'float',
        'reference_id' => 'integer',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
