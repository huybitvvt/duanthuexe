<?php

namespace App\Models;

use App\Entities\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtNote extends Model
{
    use \App\Traits\HandlesPostgresDates;

    const CLASSIFICATION_NORMAL = 'normal';
    const CLASSIFICATION_REMINDER = 'reminder';
    const CLASSIFICATION_WARNING = 'warning';
    const CLASSIFICATION_BAD_DEBT = 'bad_debt';

    protected $fillable = [
        'lease_contract_id',
        'customer_id',
        'note_content',
        'appointment_date',
        'debt_classification',
        'created_by',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
