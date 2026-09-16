<?php

namespace App\Http\Services;

use App\Entities\Customer;
use App\Models\DebtNote;
use App\Models\Bank;
use App\Support\PilotAccess;
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
        $requestedStore = data_get($data, 'store_id') ?: Store::where('kind', Store::KIND_LEASE_TO_OWN)->value('id');
        PilotAccess::store($user, $requestedStore);
        return DB::transaction(function () use ($data, $user) {
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

        Customer::findOrFail($customerId);
        $vehicleId = data_get($data, 'vehicle_id');
        $storeId = data_get($data, 'store_id');
        if (!$storeId) {
            $ltoStore = Store::where('kind', Store::KIND_LEASE_TO_OWN)->first();
            $storeId = $ltoStore ? $ltoStore->id : null;
        }

        $store = Store::findOrFail($storeId);
        if ($store->kind !== Store::KIND_LEASE_TO_OWN) {
            throw ValidationException::withMessages(['store_id' => 'Chọn kho thuê sở hữu.']);
        }
        $vehicle = Vehicle::where('id', $vehicleId)->lockForUpdate()->firstOrFail();
        if ($vehicle->status !== Vehicle::STATUS_READY || (int)($vehicle->current_store_id ?: $vehicle->store_id) !== (int)$storeId) {
            throw ValidationException::withMessages(['vehicle_id' => 'Xe phải sẵn sàng tại kho thuê sở hữu đã chọn.']);
        }
        $totalAmount = (float)data_get($data, 'total_amount', 0);
        $depositAmount = (float)data_get($data, 'deposit_amount', 0);
        $installmentCount = (int)data_get($data, 'installment_count', 12);
        if ($installmentCount < 1) {
            $installmentCount = 12;
        }

        if ($totalAmount <= 0 || $depositAmount < 0 || $depositAmount > $totalAmount || $installmentCount > 120) {
            throw ValidationException::withMessages(['total_amount' => 'Giá trị hợp đồng, trả trước hoặc số kỳ không hợp lệ.']);
        }
        $remainingToPay = $totalAmount - $depositAmount;
        $periodAmount = (float)data_get($data, 'period_amount');
        if (!$periodAmount || $periodAmount <= 0) {
            $periodAmount = round($remainingToPay / $installmentCount, 0);
        }

        if ($periodAmount * ($installmentCount - 1) > $remainingToPay) {
            throw ValidationException::withMessages(['period_amount' => 'Tổng các kỳ vượt số tiền còn phải trả.']);
        }
        $startDate = data_get($data, 'start_date') ? Carbon::parse($data['start_date']) : Carbon::now();
        $code = 'TSH-' . Carbon::now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));

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
                'end_date' => $startDate->copy()->addMonthsNoOverflow($installmentCount),
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'installment_count' => $installmentCount,
                'period_amount' => $periodAmount,
                'status' => LeaseContract::STATUS_ACTIVE,
                'assigned_user_id' => data_get($data, 'assigned_user_id', $user->id),
                'notes' => data_get($data, 'notes', ''),
            ]);

            // A promised initial payment is a due item, not a fictitious receipt.
            if ($depositAmount > 0) {
                LeaseInstallment::create([
                    'lease_contract_id' => $contract->id, 'period_number' => 0,
                    'due_date' => $startDate, 'amount_due' => $depositAmount,
                    'amount_paid' => 0, 'status' => LeaseInstallment::STATUS_UNPAID,
                ]);
            }
            // Generate installment schedule
            for ($i = 1; $i <= $installmentCount; $i++) {
                $dueDate = $startDate->copy()->addMonthsNoOverflow($i);
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
                    'status' => $currentAmount > 0 ? LeaseInstallment::STATUS_UNPAID : LeaseInstallment::STATUS_PAID,
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
        $requestKey = data_get($data, 'idempotency_key');
        $fingerprint = hash('sha256', json_encode($data));
        $targetInstallmentId = data_get($data, 'installment_id');

        return DB::transaction(function () use ($contractId, $amount, $paymentDate, $notes, $paymentMethod, $bankId, $targetInstallmentId, $user, $requestKey, $fingerprint) {
            $contract = LeaseContract::where('id', $contractId)->lockForUpdate()->firstOrFail();

            PilotAccess::store($user, $contract->store_id);
            if ($requestKey) {
                $previous = DB::table('lease_payment_requests')->where('lease_contract_id', $contractId)->where('request_key', $requestKey)->first();
                if ($previous) {
                    if ($previous->fingerprint !== $fingerprint) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Mã yêu cầu đã dùng với dữ liệu khác.']);
                    }
                    return json_decode($previous->result, true);
                }
            }
            if (!in_array($contract->status, [LeaseContract::STATUS_ACTIVE, LeaseContract::STATUS_DEFAULTED])) {
                throw ValidationException::withMessages(['contract' => 'Hợp đồng không còn nhận thanh toán.']);
            }
            $outstanding = max(0, $contract->total_amount - $contract->allocations()->sum('amount'));
            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount' => 'Khoản thu vượt dư nợ; cần xử lý trả dư qua nghiệp vụ riêng.']);
            }
            if (!in_array($paymentMethod, [1, 2])) {
                throw ValidationException::withMessages(['payment_method' => 'Phương thức thanh toán không hợp lệ.']);
            }
            $ownerType = null;
            $cashId = null;
            if ($paymentMethod === 2) {
                $bank = Bank::findOrFail($bankId);
                PilotAccess::store($user, $bank->store_id);
                if ((int)$bank->store_id !== (int)$contract->store_id) {
                    throw ValidationException::withMessages(['bank_id' => 'Tài khoản không thuộc cơ sở hợp đồng.']);
                }
                $ownerType = $bank->owner_type ?: 'unknown';
            } else {
                $bankId = null;
                $cashId = \App\Models\Cash::where('store_id', $contract->store_id)->where('status', 'Active')->value('id');
                if (!$cashId) {
                    throw ValidationException::withMessages(['payment_method' => 'Cơ sở chưa có quỹ tiền mặt đang hoạt động.']);
                }
            }
            if ($targetInstallmentId && !$contract->installments()->where('id', $targetInstallmentId)->exists()) {
                throw ValidationException::withMessages(['installment_id' => 'Kỳ thanh toán không thuộc hợp đồng.']);
            }
            // 1. Create financial transaction in ledger
            $transaction = Transaction::create([
                'value' => $amount,
                'type' => Transaction::THU,
                'payment_method' => $paymentMethod,
                'bank_id' => $bankId,
                'bank_owner_type' => $ownerType,
                'cash_id' => $cashId,
                'created_at' => $paymentDate,
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
                    'paid_at' => ($newStatus === LeaseInstallment::STATUS_PAID) ? $paymentDate : $inst->paid_at,
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

            $result = [
                'transaction_id' => $transaction->id,
                'contract_id' => $contract->id,
                'total_amount_allocated' => $amount,
                'allocations_count' => count($allocationsCreated),
            ];
            if ($requestKey) {
                DB::table('lease_payment_requests')->insert(['lease_contract_id' => $contractId, 'request_key' => $requestKey, 'fingerprint' => $fingerprint, 'result' => json_encode($result), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }
            return $result;
        });
    }

    /**
     * Add a debt collection note and appointment date.
     */
    public function addDebtNote(int $contractId, array $data, User $user): DebtNote
    {
        $contract = LeaseContract::findOrFail($contractId);
        PilotAccess::store($user, $contract->store_id);
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

        if (!PilotAccess::isAdmin($user)) {
            $query->where('store_id', $user->store_id ?: -1);
        }
        // Keyword filter
        $keyword = data_get($params, 'keyword', data_get($params, 'search'));
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

        $limit = max(1, min(10000, (int)data_get($params, 'limit', data_get($params, 'per_page', 15))));
        $bucket = data_get($params, 'aging_bucket');
        if ($bucket) {
            $today = Carbon::today('Asia/Ho_Chi_Minh');
            $open = function ($q) { $q->whereColumn('amount_due', '>', 'amount_paid'); };
            if ($bucket === 'current') {
                $query->whereDoesntHave('installments', function ($q) use ($today, $open) { $open($q); $q->where('due_date', '<', $today->toDateString()); });
            } else {
                $ranges = ['overdue_1_7' => [1,7], 'overdue_8_30' => [8,30], 'overdue_30_plus' => [31,null]];
                if (!isset($ranges[$bucket])) { throw ValidationException::withMessages(['aging_bucket' => 'Nhóm nợ không hợp lệ.']); }
                list($min,$max) = $ranges[$bucket];
                $query->whereHas('installments', function ($q) use ($today,$open,$min,$max) {
                    $open($q); $q->where('due_date', '<=', $today->copy()->subDays($min)->toDateString());
                    if ($max) { $q->where('due_date', '>=', $today->copy()->subDays($max)->toDateString()); }
                });
                if ($max) { $query->whereDoesntHave('installments', function ($q) use ($today,$open,$max) { $open($q); $q->where('due_date', '<', $today->copy()->subDays($max)->toDateString()); }); }
            }
        }
        if (!empty($params['id'])) { $query->where('id', $params['id']); }
        $contracts = $query->orderBy('id', 'desc')->paginate($limit, ['*'], 'page', max(1, (int)data_get($params, 'page', 1)));

        $today = Carbon::today('Asia/Ho_Chi_Minh');

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

        return $contracts;
    }

    /**
     * Get aggregate statistics across all lease-to-own contracts.
     */
    public function getStats(array $params, User $user): array
    {
        $query = LeaseContract::with(['installments', 'allocations']);
        if (!PilotAccess::isAdmin($user)) { $query->where('store_id', $user->store_id ?: -1); }
        $contracts = $query->where('status', '!=', LeaseContract::STATUS_CANCELLED)->get();
        $today = Carbon::today('Asia/Ho_Chi_Minh');

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
            'active_contracts' => $contracts->where('status', LeaseContract::STATUS_ACTIVE)->count(),
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
    public function show(int $contractId, ?User $user = null): LeaseContract
    {
        $contract = LeaseContract::with([
            'customer',
            'vehicle',
            'store',
            'installments.allocations.transaction',
            'allocations.transaction',
            'allocations.installment',
            'debtNotes.createdByUser',
            'assignedUser:id,name',
        ])->findOrFail($contractId);
        PilotAccess::store($user ?: auth()->user(), $contract->store_id);
        $metrics = $this->index(['id' => $contractId, 'page' => 1], $user ?: auth()->user())->first();
        foreach (['total_paid','outstanding_balance','overdue_amount','overdue_days','aging_bucket','latest_note'] as $key) { $contract->$key = $metrics->$key; }
        return $contract;
    }
}
