<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessApprovalRequest extends Model
{
    use \App\Traits\HandlesPostgresDates;

    protected $fillable = [
        'subject_type', 'subject_id', 'store_id', 'action', 'status',
        'reason', 'payload', 'requested_by', 'decided_by',
        'decision_note', 'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
