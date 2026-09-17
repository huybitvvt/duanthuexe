<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number', 'entry_date', 'store_id', 'source_type', 'source_id',
        'status', 'description', 'created_by', 'posted_by', 'posted_at',
        'reversed_entry_id', 'idempotency_key',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'store_id' => 'integer',
        'source_id' => 'integer',
        'created_by' => 'integer',
        'posted_by' => 'integer',
        'posted_at' => 'datetime',
        'reversed_entry_id' => 'integer',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedByUser()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }
}
