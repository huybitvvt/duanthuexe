<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepayPaymentRequest extends Model
{
    protected $fillable = [
        'code', 'store_id', 'bank_id', 'account_number', 'account_holder', 'order_id', 'created_by',
        'purpose', 'line_item_id', 'extension_return_at', 'extension_from_at', 'expected_amount', 'received_amount', 'status', 'note',
    ];
}
