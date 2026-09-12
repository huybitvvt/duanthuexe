<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use \App\Traits\HandlesPostgresDates;
    protected $fillable = [
        'user_id',
        'role_id'
    ];
}
