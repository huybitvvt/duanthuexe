<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVatDocument extends Model
{
    protected $table = 'accounting_vat_documents';

    protected $fillable = [
        'document_type', 'invoice_number', 'invoice_date', 'counterparty_name',
        'tax_code', 'amount_before_tax', 'vat_rate', 'vat_amount', 'total_amount',
        'payment_status', 'store_id', 'transaction_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'amount_before_tax' => 'float',
        'vat_rate' => 'float',
        'vat_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
