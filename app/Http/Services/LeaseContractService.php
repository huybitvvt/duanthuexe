<?php

namespace App\Http\Services;

use App\Entities\Customer;
use App\Models\DebtNote;
use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeasePaymentAllocation;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaseContractService
{
    /**
     * Create a new Lease-to-own contract and automatically schedule installments.
     */
    public function createContract(array $data, User $user): LeaseContract
    {
        $customerId = data_get($data, 'customer_id');
        if (!$customerId && isset($data['customer'])) {
            $cData = $data['customer'];
            $customer = Customer::firstOrCreate(
                ['phone' => $cData['phone']],
                [
                    'name' => $cData['name'] ?? '',
                    'address' => $cData['address'] ?? '',
                    'id_card' => $cData['id_card'] ?? '',
                ]
            );
            $customerId = $customer->id;
        }

        if (!$customerId) {
            throw ValidationException::withMessages([
                'customer_id' => ['Vui lòng chọn hoặc nhập thông tin khách hàng.']
            ]);
        }

        $vehicleId = data_get($data, 'vehicle_id');
        $storeId = data_get($data, 'store_id');
        if (!$storeId) {
            $ltoStore = Store::where('kind', Store::KIND_LEASE_TO_OWN)->first();
            $storeId = $ltoStore ? $ltoStore->id : null;
        }

        $totalAmount = (float)data_get($data, 'total_amount', 0);
        $depositAmount = (float)data_get($data, 'deposit_amount', 0);
        $installmentCount = (int)data_get($data, 'installment_count', 12);
        if ($installmentCount < 1) {
            $installmentCount = 12;
        }

        $remainingToPay = max(0, $totalAmount - $depositAmount);
        $periodAmount = (float)data_get($data, 'period_amount');
        if (!$periodAmount || $periodAmount <= 0) {
            $periodAmount = round($remainingToPay / $installmentCount, 0);
        }

        $startDate = data_get($data, 'start_date') ? Carbon::parse($data['start_date']) : Carbon::now();
        $code = 'TSH-' . Carbon::now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return DB::transaction(function () use (
            $code, $customerId, $vehicleId, $storeId, $startDate, $totalAmount,
            $depositAmount, $installmentCount, $periodAmount, $data, $user
        ) {
            $contract = LeaseContract::create([
                'contract_code' => $code,
                'customer_id' => $customerId,
                'vehicle_id' => $vehicleId,
                'store_id' => $storeId,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addMonths($installmentCount),
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'installment_count' => $installmentCount,
                'period_amount' => $periodAmount,
                'status' => LeaseContract::STATUS_ACTIVE,
                'assigned_user_id' => data_get($data, 'assigned_user_id', $user->id),
                'notes' => data_get($data, 'notes', ''),
            ]);

            // Generate installment schedule
            for ($i = 1; $i <= $installmentCount; $i++) {
                $dueDate = $startDate->copy()->addMonths($i);
                // Adjust for last installment rounding diff if any
                $currentAmount = ($i === $installmentCount)
                    ? ($totalAmount - $depositAmount - ($periodAmount * ($installmentCount - 1)))
                    : $periodAmount;

                LeaseInstallment::create([
                    'lease_contract_id' => $contract->id,
                    'period_number' => $i,
                    'due_date' => $dueDate,
                    'amount_due' => max(0, $currentAmount),
                    'amount_paid' => 0,
                    'status' => LeaseInstallment::STATUS_UNPAID,
                ]);
            }

            // Update vehicle status to using
            if ($vehicleId) {
                Vehicle::where('id', $vehicleId)->update([
                    'status' => Vehicle::STATUS_USING,
                ]);
            }

            return $contract->load(['customer', 'vehicle', 'store', 'installments']);
        });
    }

    /**
     * Allocate payment into installment schedule, generating cash/bank transaction.
     */
    public function allocatePayment(int $contractId, array $data, User $user): array
    {
        $amount = (float)data_get($data, 'amount', 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Số tiền thanh toán phải lớn hơn 0.']
            ]);
        }

        $paymentDate = data_get($data, 'payment_date') ? Carbon::parse($data['payment_date']) : Carbon::now();
        $notes = (string)data_get($data, 'notes', 'Thu tiền góp hợp đồng thuê sở hữu');
        $paymentMethod = (int)data_get($data, 'payment_method', 1); // 1: TM, 2: CK
        $bankId = data_get($data, 'bank_id');
        $targetInstallmentId = data_get($data, 'installment_id');

        return DB::transaction(function () use ($contractId, $amount, $paymentDate, $notes, $paymentMethod, $bankId, $targetInstallmentId, $user) {
            $contract = LeaseContract::where('id', $contractId)->lockForUpdate()->firstOrFail();

            // 1. Create financial transaction in ledger
            $transaction = Transaction::create([
                'value' => $amount,
                'type' => Transaction::THU,
                'payment_method' => $paymentMethod,
                'bank_id' => $bankId,
                'store_id' => $contract->store_id,
                'user_id' => $user->id,
                'name' => "Thu tiền HĐ {$contract->contract_code}",
                'desc' => "Thu tiền HĐ Thuê sở hữu {$contract->contract_code}: {$notes}",
            ]);

            // 2. Query installments in chronological order
            $query = LeaseInstallment::where('lease_contract_id', $contract->id)
                ->where('status', '!=', LeaseInstallment::STATUS_PAID)
                ->orderBy('period_number', 'asc');

            if ($targetInstallmentId) {
                // Prioritize the requested installment first, then subsequent
                $installments = LeaseInstallment::where('lease_contract_id', $contract->id)
                    ->where('id', $targetInstallmentId)
                    ->get();
                $others = (clone $query)->where('id', '!=', $targetInstallmentId)->get();
                $installments = $installments->merge($others);
            } else {
                $installments = $query->get();
            }

            $remaining = $amount;
            $allocationsCreated = [];

            foreach ($installments as $inst) {
                if ($remaining <= 0) {
                    break;
                }

                $due = $inst->amount_due - $inst->amount_paid;
                if ($due <= 0) {
                    continue;
                }

                $allocated = min($remaining, $due);
                $newPaid = $inst->amount_paid + $allocated;
                $newStatus = ($newPaid >= $inst->amount_due) ? LeaseInstallment::STATUS_PAID : LeaseInstallment::STATUS_PARTIALLY_PAID;

                $inst->update([
                    'amount_paid' => $newPaid,
                    'status' => $newStatus,
                    'paid_at' => ($newStatus === LeaseInstallment::STATUS_PAID) ? Carbon::now() : $inst->paid_at,
                ]);

                $alloc = LeasePaymentAllocation::create([
                    'lease_contract_id' => $contract->id,
                    'installment_id' => $inst->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $allocated,
                    'payment_date' => $paymentDate,
                    'notes' => "Kỳ #{$inst->period_number}: {$notes}",
                    'created_by' => $user->id,
                ]);

                $allocationsCreated[] = $alloc;
                $remaining -= $allocated;
            }

            // If there's still excess money (overpaid beyond all installments)
            if ($remaining > 0) {
                $alloc = LeasePaymentAllocation::create([
                    'lease_contract_id' => $contract->id,
                    'installment_id' => null,
                    'transaction_id' => $transaction->id,
                    'amount' => $remaining,
                    'payment_date' => $paymentDate,
                    'notes' => "Thanh toán dư / Trả trước: {$notes}",
                    'created_by' => $user->id,
                ]);
                $allocationsCreated[] = $alloc;
            }

            // Check if all installments are fully paid
            $unpaidCount = LeaseInstallment::where('lease_contract_id', $contract->id)
                ->where('status', '!=', LeaseInstallment::STATUS_PAID)
                ->count();

            if ($unpaidCount === 0) {
                $contract->update(['status' => LeaseContract::STATUS_COMPLETED]);
            }

            return [
                'transaction_id' => $transaction->id,
                'contract_id' => $contract->id,
                'total_amount_allocated' => $amount,
                'allocations_count' => count($allocationsCreated),
            ];
        });
    }

    /**
     * Add a debt collection note and appointment date.
     */
    public function addDebtNote(int $contractId, array $data, User $user): DebtNote
    {
        $contract = LeaseContract::findOrFail($contractId);
        $content = (string)data_get($data, 'note_content', '');
        if (!$content) {
            throw ValidationException::withMessages([
                'note_content' => ['Vui lòng nhập nội dung ghi chú nhắc nợ.']
            ]);
        }

        $appointmentDate = data_get($data, 'appointment_date') ? Carbon::parse($data['appointment_date']) : null;
        $classification = data_get($data, 'debt_classification', DebtNote::CLASSIFICATION_NORMAL);

        return DebtNote::create([
            'lease_contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'note_content' => $content,
            'appointment_date' => $appointmentDate,
            'debt_classification' => $classification,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Get paginated contracts with calculated debt aging and status.
     */
    public function index(array $params, User $user)
    {
        $query = LeaseContract::with([
            'customer',
            'vehicle',
            'store',
            'installments',
            'debtNotes.createdByUser',
            'assignedUser:id,name',
            'allocations',
        ]);

        // Keyword filter
        $keyword = data_get($params, 'keyword');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('contract_code', 'LIKE', "%{$keyword}%")
                  ->orWhereHas('customer', function ($sub) use ($keyword) {
                      $sub->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('phone', 'LIKE', "%{$keyword}%")
                          ->orWhere('id_card', 'LIKE', "%{$keyword}%");
                  })
                  ->orWhereHas('vehicle', function ($sub) use ($keyword) {
                      $sub->where('license', 'LIKE', "%{$keyword}%")
                          ->orWhere('name', 'LIKE', "%{$keyword}%");
                  });
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (isset($params['assigned_user_id']) && $params['assigned_user_id'] !== '') {
            $query->where('assigned_user_id', $params['assigned_user_id']);
        }

        $limit = (int)data_get($params, 'limit', 15);
        $contracts = $query->orderBy('id', 'desc')->paginate($limit);

        $today = Carbon::today();

        // Calculate dynamic debt metrics for each contract
        $contracts->getCollection()->transform(function ($contract) use ($today) {
            $totalPaid = $contract->allocations->sum('amount');
            $outstanding = max(0, $contract->total_amount - $totalPaid);

            // Find overdue installments (due_date < today and not paid)
            $overdueList = $contract->installments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->lt($today) && $inst->status !== LeaseInstallment::STATUS_PAID;
            });

            $overdueAmount = $overdueList->sum(function ($inst) {
                return max(0, $inst->amount_due - $inst->amount_paid);
            });

            $maxOverdueDays = 0;
            if ($overdueList->isNotEmpty()) {
                $earliestDue = $overdueList->min('due_date');
                $maxOverdueDays = Carbon::parse($earliestDue)->diffInDays($today);
            }

            // Aging bucket
            $bucket = 'current';
            if ($maxOverdueDays > 30) {
                $bucket = 'overdue_30_plus';
            } elseif ($maxOverdueDays >= 8) {
                $bucket = 'overdue_8_30';
            } elseif ($maxOverdueDays >= 1) {
                $bucket = 'overdue_1_7';
            }

            // Current due installment (next upcoming or overdue)
            $currentInstallment = $contract->installments->first(function ($inst) {
                return $inst->status !== LeaseInstallment::STATUS_PAID;
            });

            // Latest note
            $latestNote = $contract->debtNotes->first();

            $contract->total_paid = $totalPaid;
            $contract->outstanding_balance = $outstanding;
            $contract->overdue_amount = $overdueAmount;
            $contract->overdue_days = $maxOverdueDays;
            $contract->aging_bucket = $bucket;
            $contract->current_installment = $currentInstallment ? [
                'period_number' => $currentInstallment->period_number,
                'due_date' => $currentInstallment->due_date ? Carbon::parse($currentInstallment->due_date)->format('d/m/Y') : null,
                'amount_due' => $currentInstallment->amount_due,
                'amount_paid' => $currentInstallment->amount_paid,
                'remaining' => max(0, $currentInstallment->amount_due - $currentInstallment->amount_paid),
                'status' => $currentInstallment->status,
            ] : null;
            $contract->latest_note = $latestNote ? [
                'content' => $latestNote->note_content,
                'appointment_date' => $latestNote->appointment_date ? Carbon::parse($latestNote->appointment_date)->format('d/m/Y') : null,
                'classification' => $latestNote->debt_classification,
                'created_by' => $latestNote->createdByUser ? $latestNote->createdByUser->name : 'N/A',
                'created_at' => $latestNote->created_at ? $latestNote->created_at->format('d/m/Y H:i') : null,
            ] : null;

            return $contract;
        });

        // Filter by aging bucket in-memory if specified
        $filterBucket = data_get($params, 'aging_bucket');
        if ($filterBucket) {
            $filtered = $contracts->getCollection()->filter(function ($c) use ($filterBucket) {
                return $c->aging_bucket === $filterBucket;
            })->values();
            $contracts->setCollection($filtered);
        }

        return $contracts;
    }

    /**
     * Get aggregate statistics across all lease-to-own contracts.
     */
    public function getStats(array $params, User $user): array
    {
        $contracts = LeaseContract::with(['installments', 'allocations'])->get();
        $today = Carbon::today();

        $totalValue = $contracts->sum('total_amount');
        $totalCollected = $contracts->sum(function ($c) {
            return $c->allocations->sum('amount');
        });
        $totalOutstanding = max(0, $totalValue - $totalCollected);

        $totalOverdue = 0;
        $bucketCounts = [
            'current' => 0,
            'overdue_1_7' => 0,
            'overdue_8_30' => 0,
            'overdue_30_plus' => 0,
        ];
        $bucketAmounts = [
            'current' => 0,
            'overdue_1_7' => 0,
            'overdue_8_30' => 0,
            'overdue_30_plus' => 0,
        ];

        foreach ($contracts as $contract) {
            if ($contract->status === LeaseContract::STATUS_COMPLETED || $contract->status === LeaseContract::STATUS_CANCELLED) {
                continue;
            }

            $overdueList = $contract->installments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->lt($today) && $inst->status !== LeaseInstallment::STATUS_PAID;
            });

            $cOverdueAmount = $overdueList->sum(function ($inst) {
                return max(0, $inst->amount_due - $inst->amount_paid);
            });
            $totalOverdue += $cOverdueAmount;

            $maxOverdueDays = 0;
            if ($overdueList->isNotEmpty()) {
                $earliestDue = $overdueList->min('due_date');
                $maxOverdueDays = Carbon::parse($earliestDue)->diffInDays($today);
            }

            if ($maxOverdueDays > 30) {
                $bucketCounts['overdue_30_plus']++;
                $bucketAmounts['overdue_30_plus'] += $cOverdueAmount;
            } elseif ($maxOverdueDays >= 8) {
                $bucketCounts['overdue_8_30']++;
                $bucketAmounts['overdue_8_30'] += $cOverdueAmount;
            } elseif ($maxOverdueDays >= 1) {
                $bucketCounts['overdue_1_7']++;
                $bucketAmounts['overdue_1_7'] += $cOverdueAmount;
            } else {
                $bucketCounts['current']++;
                $bucketAmounts['current'] += max(0, $contract->total_amount - $contract->allocations->sum('amount'));
            }
        }

        return [
            'total_contracts' => $contracts->count(),
            'total_contract_value' => $totalValue,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'total_overdue' => $totalOverdue,
            'buckets' => [
                'counts' => $bucketCounts,
                'amounts' => $bucketAmounts,
            ],
        ];
    }

    /**
     * Show detailed contract with all installment periods and allocations.
     */
    public function show(int $contractId): LeaseContract
    {
        return LeaseContract::with([
            'customer',
            'vehicle',
            'store',
            'installments.allocations.transaction',
            'allocations.transaction',
            'debtNotes.createdByUser',
            'assignedUser:id,name',
        ])->findOrFail($contractId);
    }
}
