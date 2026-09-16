<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $table = 'staff_attendances';

    protected $fillable = [
        'staff_id', 'store_id', 'attendance_date', 'clock_in_at',
        'clock_out_at', 'work_minutes', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'clock_in_at' => 'datetime:Y-m-d H:i:s',
        'clock_out_at' => 'datetime:Y-m-d H:i:s',
        'work_minutes' => 'integer',
    ];

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
