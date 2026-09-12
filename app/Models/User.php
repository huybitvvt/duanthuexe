<?php

namespace App\Models;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Entities\Role;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements JWTSubject, CanResetPasswordContract
{
    use Notifiable;
    use CanResetPassword;
    use SoftDeletes; 

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'referral_code', 'role', 'status', 'store_id', 'address', 'role_id', 'phone'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'file' => 'array',
    ];

    /**
     * PostgreSQL returns timestamptz values with a UTC offset and, when
     * present, microseconds (for example: 2026-09-12 16:31:11.278902+00).
     * Laravel 5.8's default parser expects MySQL's Y-m-d H:i:s format and
     * throws "Trailing data" while serializing the authenticated user.
     * Parse date strings through Carbon so both database formats work.
     */
    protected function asDateTime($value)
    {
        if (is_string($value)) {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        }

        return parent::asDateTime($value);
    }


    /**
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function role_rel()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'role_id', 'user_id');
    }
    public function leadLogs(){
        return $this->hasMany(LeadLog::class,'user_id','id');
    }
   
    public function leads()
    {
        return $this->hasMany(Lead::class,'user_id','id');
    }
}
