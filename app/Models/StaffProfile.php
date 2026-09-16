<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    protected $table = 'staff_profiles';

    protected $fillable = [
        'user_id',
        'staff_code',
        'full_name',
        'phone',
        'email',
        'id_card',
        'department_id',
        'position',
        'store_id',
        'status',
        'joined_at',
        'notes',
    ];

    protected $casts = [
        'joined_at' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function dutySchedules()
    {
        return $this->hasMany(StoreDutySchedule::class, 'staff_id');
    }
}
