<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAsset extends Model
{
    protected $table = 'business_assets';

    protected $fillable = [
        'asset_code', 'name', 'category', 'store_id', 'purchase_date',
        'purchase_cost', 'residual_value', 'depreciation_months', 'status',
        'notes', 'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'purchase_cost' => 'float',
        'residual_value' => 'float',
        'depreciation_months' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function getMonthlyDepreciationAttribute(): float
    {
        if ($this->depreciation_months <= 0) {
            return 0.0;
        }

        return round(max(0, $this->purchase_cost - $this->residual_value) / $this->depreciation_months, 2);
    }
}
