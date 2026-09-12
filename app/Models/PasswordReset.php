<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class PasswordReset extends Model
{
    use \App\Traits\HandlesPostgresDates;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'password_resets';
    protected $fillable = ['email', 'token'];
}
