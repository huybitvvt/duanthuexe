<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
    ];

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class, 'department_id');
    }
}
