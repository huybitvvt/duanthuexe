<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderContactLog extends Model
{
    protected $fillable = ['reminder_id', 'user_id', 'contact_date', 'note'];
}
