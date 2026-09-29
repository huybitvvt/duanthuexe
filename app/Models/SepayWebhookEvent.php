<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepayWebhookEvent extends Model
{
    protected $fillable = [
        'sepay_transaction_id', 'payment_request_id', 'transaction_id', 'excess_transaction_id',
        'payment_code', 'account_number', 'reference_code', 'amount',
        'status', 'content',
    ];
}
