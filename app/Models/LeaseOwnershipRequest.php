<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseOwnershipRequest extends Model
{
    use \App\Traits\HandlesPostgresDates;

    protected $table = 'lease_ownership_requests';

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXECUTED = 'executed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lease_contract_id',
        'vehicle_id',
        'customer_id',
        'store_id',
        'status',
        'total_contract_amount',
        'total_paid_amount',
        'discount_amount',
        'remaining_debt',
        'unpaid_installments_count',
        'checklist_documents',
        'requested_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_reason',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'executed_by',
        'executed_at',
        'execution_notes',
        'idempotency_key',
    ];

    protected $casts = [
        'checklist_documents' => 'array',
        'total_contract_amount' => 'float',
        'total_paid_amount' => 'float',
        'discount_amount' => 'float',
        'remaining_debt' => 'float',
        'unpaid_installments_count' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Entities\Customer::class, 'customer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function events()
    {
        return $this->hasMany(LeaseOwnershipEvent::class, 'ownership_request_id')->orderBy('created_at', 'asc');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
