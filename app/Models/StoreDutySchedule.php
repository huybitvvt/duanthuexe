<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreDutySchedule extends Model
{
    protected $table = 'store_duty_schedules';

    protected $fillable = [
        'store_id',
        'duty_date',
        'shift_name',
        'staff_id',
        'staff_name',
        'staff_phone',
        'role_in_shift',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'duty_date' => 'date:Y-m-d',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
