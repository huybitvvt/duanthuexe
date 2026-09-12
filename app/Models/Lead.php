<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use App\Models\LeadLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{   
    use SoftDeletes;
    protected $dates   = [
        'rent_at',  'return_at','created_at'
         
    ];
    
    public function setRentAtAttribute($value)
    {
        if(!$value){return;}
        $this->attributes['rent_at'] =  date('Y-m-d H:i:s', strtotime($value));     
    }
    public function setCreatedAtAttribute($value)
    {
        if(!$value){return;}
        $this->attributes['created_at'] =  date('Y-m-d H:i:s', strtotime($value));     
    }
    public function setReturnAtAttribute($value)
    {  
        if(!$value){return;}
        $this->attributes['return_at'] =  date('Y-m-d H:i:s', strtotime($value));  
         
    }
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($lead) {
           
            $changes = $lead->getChanges();
            if (array_key_exists('status', $changes) && $changes['status'] === 'deleted') {
                return;
            }
            $oldValues = [];
            unset($changes['updated_at']);
          
           
            foreach ($changes as $attribute => $newValue) {
                    $oldValues[$attribute] = $lead->getOriginal($attribute);
            }
            $data = [
                'old' => $oldValues,
                'new' =>   $changes
            ];

            $result = [];
            foreach ($data as $outerKey => $innerArray) {
                foreach ($innerArray as $innerKey => $value) {
                    $result[$innerKey] = [
                        "colName" => $innerKey,
                        "old" => $data["old"][$innerKey] ?? null,
                        "new" => $data["new"][$innerKey] ?? null
                    ];
                }
            }
        
              
            LeadLog::create([
                'lead_id' => $lead->id,
                'user_id' => $lead->user_id,
                'content' => "Sửa lead số " . $lead->id, 
                'metadata' => json_encode($result),
            ]);
             
            
           

        });

    }

    protected $fillable = [
        'entry_id',
        'order_id',
        'customer_name',
        'customer_phone',
        'vehicle_name',
        'store_id',
        'pickup_location',
        'rent_at',
        'return_at',
        'status',
        'note',
        'user_id',
        'created_at'
    ];


    public function stores(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'store_id', 'id');
    }
    
    public function leadLogs(){
        return $this->hasMany(LeadLog::class,'lead_id','id');
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
