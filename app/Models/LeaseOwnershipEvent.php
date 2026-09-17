<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseOwnershipEvent extends Model
{
    use \App\Traits\HandlesPostgresDates;

    public $timestamps = false;

    protected $table = 'lease_ownership_events';

    protected $fillable = [
        'ownership_request_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(LeaseOwnershipRequest::class, 'ownership_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
