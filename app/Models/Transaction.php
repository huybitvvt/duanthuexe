<?php

namespace App\Models;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Class Transaction.
 *
 * @package namespace App\Entities;
 */
class Transaction extends Model implements Transformable
{
    use \App\Traits\HandlesPostgresDates;
    use TransformableTrait;

    const THU = 'in';
    const CHI = 'out';
    const ADDON = 'addon';
    const types = [self::THU, self::CHI];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['order_id', 'order_item_id','name', 'type', 'value', 'note', 'status', 'user_id', 'store_id','payment_method','bank_id','bank_owner_type','cash_id', 'created_at', 'updated_at', 'desc', 'object_name', 'object_type', 'object_id'];
    protected static function boot()
        {
            parent::boot();

            static::creating(function ($transaction) {
                if ($transaction->bank_id && Schema::hasColumn('transactions', 'bank_owner_type')) {
                    $bank = Bank::find($transaction->bank_id);
                    if ($bank) {
                        $transaction->bank_owner_type = $bank->owner_type ?: Bank::OWNER_UNKNOWN;
                    }
                }
            });
 
            static::updated(function ($transaction) {
               
                $changes = $transaction->getChanges();
                $oldValues = [];
                unset($changes['updated_at']);
              
               
                foreach ($changes as $attribute => $newValue) {
                        $oldValues[$attribute] = $transaction->getOriginal($attribute);
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
            // dd($result);
                  
                ActivityLog::create([
                    'transaction_id' => $transaction->id,
                    'user_id' => Auth::id(),
                    'name' => 'receipt:' . $transaction->id,
                    'action' => 'update',
                    'content' => "Sửa phiếu số " . $transaction->id, 
                    'metadata' => json_encode($result),
                ]);
                 
                
               

            });
        }
    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id')->withDefault();
    }
    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id')->withDefault();
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id')->withDefault();
    }
  
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'transaction_id', 'id');
    }

}
