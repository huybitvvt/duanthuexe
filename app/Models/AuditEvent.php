<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use \App\Traits\HandlesPostgresDates;

    public $timestamps = false;

    protected $table = 'audit_events';

    protected $fillable = [
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'store_id',
        'before_json',
        'after_json',
        'reason',
        'request_id',
        'ip_hash',
        'created_at',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
