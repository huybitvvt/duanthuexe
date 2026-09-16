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
        $notes = (string)data_get($data, 'notes', data_get($data, 'note', 'Thu tiền góp hợp đồng thuê sở hữu'));
        $rawMethod = data_get($data, 'payment_method', 1);
        if ($rawMethod === 'TM' || $rawMethod === 'cash') {
            $paymentMethod = 1;
        } elseif ($rawMethod === 'CK' || $rawMethod === 'bank') {
            $paymentMethod = 2;
        } else {
            $paymentMethod = (int)$rawMethod ?: 1;
        }
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
            $activeAllocated = (float) $contract->allocations()->effectivePayments()->sum('amount');
            $discount = (float) ($contract->discount_amount ?? 0);
            $outstanding = max(0, (float) $contract->total_amount - $discount - $activeAllocated);
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
     * Tất toán hợp đồng thuê sở hữu (Early settlement / Settle full remaining debt).
     */
    public function settleContract(int $contractId, array $data, User $user): LeaseContract
    {
        $contract = LeaseContract::with(['installments', 'allocations'])->findOrFail($contractId);
        PilotAccess::store($user, $contract->store_id);

        if ($contract->status === LeaseContract::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['contract' => 'Hợp đồng này đã được tất toán trước đó.']);
        }

        return DB::transaction(function () use ($contract, $data, $user) {
            $contract = LeaseContract::where('id', $contract->id)->lockForUpdate()->firstOrFail();

            // Calculate active total paid and remaining debt
            $totalPaid = (float) $contract->allocations()->effectivePayments()->sum('amount');
            $currentDiscount = (float) ($contract->discount_amount ?? 0);
            $remainingDebt = max(0, (float) $contract->total_amount - $currentDiscount - $totalPaid);

            if ($remainingDebt <= 0) {
                $contract->status = LeaseContract::STATUS_COMPLETED;
                $contract->settled_at = Carbon::now();
                $contract->save();
                return $contract;
            }

            $settlementAmount = (float) data_get($data, 'settlement_amount', 0);
            $discountAmount = (float) data_get($data, 'discount_amount', 0);
            $rawMethod = data_get($data, 'payment_method', 1);
            if ($rawMethod === 'TM' || $rawMethod === 'cash') {
                $paymentMethod = 1;
            } elseif ($rawMethod === 'CK' || $rawMethod === 'bank') {
                $paymentMethod = 2;
            } else {
                $paymentMethod = (int)$rawMethod ?: 1;
            }
            $bankId = data_get($data, 'bank_id');
            $bankOwnerType = data_get($data, 'bank_owner_type', 'personal');
            $notes = (string) data_get($data, 'notes', data_get($data, 'note', 'Tất toán hợp đồng'));
            $idempotencyKey = data_get($data, 'idempotency_key');

            // 1. Chặn tất toán 0 đồng hoặc âm khi còn dư nợ
            if ($settlementAmount <= 0) {
                throw ValidationException::withMessages([
                    'settlement_amount' => 'Số tiền tất toán phải lớn hơn 0 để hoàn tất hợp đồng còn nợ (' . number_format($remainingDebt, 0, ',', '.') . ' đ).'
                ]);
            }

            // Chặn khoản thu vượt dư nợ còn lại
            if ($settlementAmount > $remainingDebt) {
                throw ValidationException::withMessages([
                    'settlement_amount' => 'Số tiền tất toán (' . number_format($settlementAmount, 0, ',', '.') . ' đ) vượt quá tổng dư nợ còn lại (' . number_format($remainingDebt, 0, ',', '.') . ' đ).'
                ]);
            }

            // 2. Kiểm tra quyền chiết khấu
            if ($discountAmount > 0 && !PilotAccess::isAdmin($user)) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Chỉ Quản trị viên mới có quyền áp dụng chiết khấu khi tất toán hợp đồng.'
                ]);
            }

            // 3. Kiểm tra số tiền tất toán + chiết khấu phải đủ bù đắp dư nợ
            if (($settlementAmount + $discountAmount) < ($remainingDebt - 0.01)) {
                throw ValidationException::withMessages([
                    'settlement_amount' => 'Số tiền tất toán kèm chiết khấu (' . number_format($settlementAmount + $discountAmount, 0, ',', '.') . ' đ) không đủ để tất toán toàn bộ dư nợ (' . number_format($remainingDebt, 0, ',', '.') . ' đ).'
                ]);
            }


            if (($settlementAmount + $discountAmount) > ($remainingDebt + 0.01)) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Số tiền thực thu và chiết khấu vượt dư nợ còn lại. Vui lòng nhập đúng phần nghĩa vụ cần tất toán.'
                ]);
            }

            // 4. Phân bổ tiền thanh toán thực tế vào các kỳ
            $this->allocatePayment($contract->id, [
                'amount' => min($settlementAmount, $remainingDebt),
                'payment_date' => Carbon::now()->toDateString(),
                'payment_method' => $paymentMethod,
                'bank_id' => $bankId,
                'bank_owner_type' => $bankOwnerType,
                'notes' => $notes . ($discountAmount > 0 ? " (Chiết khấu duyệt: " . number_format($discountAmount, 0, ',', '.') . " đ)" : ""),
                'idempotency_key' => $idempotencyKey,
            ], $user);

            // 5. Chiết khấu là điều chỉnh nghĩa vụ, không phải tiền đã thu.
            if ($discountAmount > 0 && ($settlementAmount + $discountAmount) >= ($remainingDebt - 0.01)) {
                $contract->discount_amount = $currentDiscount + $discountAmount;
                $remainingDiscount = $discountAmount;
                $installments = $contract->installments()->with('allocations')->orderBy('period_number')->get();

                foreach ($installments as $installment) {
                    if ($remainingDiscount <= 0.01) {
                        break;
                    }

                    $remainingObligation = $installment->remaining_amount;
                    if ($remainingObligation <= 0) {
                        continue;
                    }

                    $adjusted = min($remainingDiscount, $remainingObligation);
                    LeasePaymentAllocation::create([
                        'lease_contract_id' => $contract->id,
                        'installment_id' => $installment->id,
                        'transaction_id' => null,
                        'amount' => $adjusted,
                        'payment_date' => Carbon::now()->toDateString(),
                        'notes' => 'Chiết khấu tất toán được duyệt bởi ' . $user->name,
                        'status' => LeasePaymentAllocation::STATUS_DISCOUNT,
                        'created_by' => $user->id,
                    ]);

                    $remainingDiscount -= $adjusted;
                    $effectiveSettled = (float)$installment->amount_paid + (float)$installment->adjustment_amount + $adjusted;
                    if ($effectiveSettled >= ((float)$installment->amount_due - 0.01)) {
                        $installment->status = LeaseInstallment::STATUS_PAID;
                        $installment->paid_at = Carbon::now();
                    } else {
                        $installment->status = LeaseInstallment::STATUS_PARTIALLY_PAID;
                    }
                    $installment->notes = trim(($installment->notes ? $installment->notes . "\n" : "") . "Điều chỉnh chiết khấu: " . number_format($adjusted, 0, ',', '.') . " đ duyệt bởi " . $user->name);
                    $installment->save();
                }

                if ($remainingDiscount > 0.01) {
                    throw new \RuntimeException('Không thể phân bổ hết chiết khấu vào lịch kỳ thanh toán.');
                }
            }

            $unpaidCount = $contract->installments()->where('status', '!=', LeaseInstallment::STATUS_PAID)->count();
            if ($unpaidCount === 0) {
                $contract->status = LeaseContract::STATUS_COMPLETED;
                $contract->settled_at = Carbon::now();
            }
            $contract->notes = trim(($contract->notes ? $contract->notes . "\n" : "") . "Đã tất toán ngày " . Carbon::now()->format('d/m/Y') . " bởi " . $user->name . ". Số tiền thực thu: " . number_format($settlementAmount, 0, ',', '.') . " đ" . ($discountAmount > 0 ? " (Chiết khấu: " . number_format($discountAmount, 0, ',', '.') . " đ)" : "") . ". " . $notes);
            $contract->save();

            return $contract;
        });
    }

    /**
     * Đảo thu (Reversal) giao dịch thu tiền kỳ thuê sở hữu.
     */
    public function reverseAllocation(int $allocationId, string $reason, User $user): array
    {
        if (!PilotAccess::isAdmin($user)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Chỉ Admin hoặc Kế toán mới có quyền đảo thu.');
        }

        if (empty(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do đảo thu bắt buộc.']);
        }

        return DB::transaction(function () use ($allocationId, $reason, $user) {
            $allocation = LeasePaymentAllocation::with(['installment', 'contract', 'transaction'])->lockForUpdate()->findOrFail($allocationId);
            PilotAccess::store($user, $allocation->contract->store_id);

            if ($allocation->status === LeasePaymentAllocation::STATUS_REVERSED) {
                throw ValidationException::withMessages(['allocation' => 'Khoản phân bổ này đã được đảo thu trước đó.']);
            }

            if ($allocation->status === LeasePaymentAllocation::STATUS_DISCOUNT) {
                throw ValidationException::withMessages(['allocation' => 'Chiết khấu là điều chỉnh nghĩa vụ và không thể đảo như một phiếu thu.']);
            }

            $installment = $allocation->installment;
            $amount = (float) $allocation->amount;

            // Giảm số tiền đã thanh toán của kỳ
            if ($installment) {
                $newPaid = max(0, (float) $installment->amount_paid - $amount);
                $installment->amount_paid = $newPaid;
                $discountAdjustment = (float)$installment->allocations()
                    ->discountAdjustments()
                    ->sum('amount');
                $effectiveSettled = $newPaid + $discountAdjustment;
                if ($effectiveSettled >= ((float)$installment->amount_due - 0.01)) {
                    $installment->status = LeaseInstallment::STATUS_PAID;
                } elseif ($effectiveSettled <= 0) {
                    $installment->status = LeaseInstallment::STATUS_UNPAID;
                    $installment->paid_at = null;
                } else {
                    $installment->status = LeaseInstallment::STATUS_PARTIALLY_PAID;
                    $installment->paid_at = null;
                }
                $installment->save();
            }

            // Xác định thông tin quỹ/ngân hàng từ giao dịch gốc để liên kết sổ chuẩn xác
            $origTx = $allocation->transaction;
            $paymentMethod = $origTx ? (int)$origTx->payment_method : 1;
            $cashId = $origTx ? $origTx->cash_id : null;
            $bankId = $origTx ? $origTx->bank_id : null;
            $bankOwnerType = $origTx ? $origTx->bank_owner_type : null;

            if ($paymentMethod === 1 && !$cashId) {
                $cashId = \App\Models\Cash::where('store_id', $allocation->contract->store_id)->where('status', 'Active')->value('id');
            }

            // Ghi nhận phiếu chi đối ứng (Reversal transaction) có đầy đủ cash_id / bank_id
            $revTrans = Transaction::create([
                'order_id' => null,
                'name' => 'Đảo thu đợt #' . ($installment ? $installment->period_number : $allocation->id) . ' HĐ ' . $allocation->contract->contract_code,
                'type' => Transaction::CHI,
                'value' => $amount,
                'payment_method' => $paymentMethod,
                'cash_id' => $cashId,
                'bank_id' => $bankId,
                'bank_owner_type' => $bankOwnerType,
                'note' => 'Đảo thu phân bổ #' . $allocation->id . ' - Lý do: ' . $reason,
                'status' => 1,
                'user_id' => $user->id,
                'store_id' => $allocation->contract->store_id,
                'desc' => 'Đảo thu kỳ trả góp thuê sở hữu',
            ]);

            // Cập nhật trạng thái đảo thu trên bản ghi phân bổ gốc (KHÔNG xóa cứng để giữ toàn vẹn dữ liệu và đối chiếu sổ)
            $allocation->status = LeasePaymentAllocation::STATUS_REVERSED;
            $allocation->reversal_transaction_id = $revTrans->id;
            $allocation->reversal_reason = $reason;
            $allocation->reversed_at = Carbon::now();
            $allocation->reversed_by = $user->id;
            $allocation->save();

            $activePaid = (float)$allocation->contract->allocations()->effectivePayments()->sum('amount');
            $effectiveObligation = max(0, (float)$allocation->contract->total_amount - (float)($allocation->contract->discount_amount ?? 0));
            if (($effectiveObligation - $activePaid) > 0.01) {
                $allocation->contract->status = LeaseContract::STATUS_ACTIVE;
                $allocation->contract->settled_at = null;
            } else {
                $allocation->contract->status = LeaseContract::STATUS_COMPLETED;
            }
            $allocation->contract->save();

            return [
                'success' => true,
                'reversed_allocation_id' => $allocationId,
                'amount_reversed' => $amount,
                'reversal_transaction_id' => $revTrans->id,
                'reason' => $reason,
            ];
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
            'installments.allocations',
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
            $totalPaid = (float) $contract->allocations->filter(function ($a) {
                return $a->status === null || $a->status === LeasePaymentAllocation::STATUS_ACTIVE;
            })->sum('amount');
            $discount = (float) ($contract->discount_amount ?? 0);
            $effectiveObligation = max(0, (float) $contract->total_amount - $discount);
            $outstanding = max(0, $effectiveObligation - $totalPaid);

            // Find overdue installments (due_date < today and not paid)
            $overdueList = $contract->installments->filter(function ($inst) use ($today) {
                return Carbon::parse($inst->due_date)->lt($today) && $inst->status !== LeaseInstallment::STATUS_PAID;
            });

            $overdueAmount = $overdueList->sum(function ($inst) {
                return $inst->remaining_amount;
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
                'adjustment_amount' => $currentInstallment->adjustment_amount,
                'remaining' => $currentInstallment->remaining_amount,
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
        $query = LeaseContract::with(['installments.allocations', 'allocations']);
        if (!PilotAccess::isAdmin($user)) { $query->where('store_id', $user->store_id ?: -1); }
        $contracts = $query->where('status', '!=', LeaseContract::STATUS_CANCELLED)->get();
        $today = Carbon::today('Asia/Ho_Chi_Minh');

        $totalValue = $contracts->sum('total_amount');
        $totalCollected = $contracts->sum(function ($c) {
            return (float) $c->allocations->filter(function ($a) {
                return $a->status === null || $a->status === LeasePaymentAllocation::STATUS_ACTIVE;
            })->sum('amount');
        });
        $totalDiscounts = (float)$contracts->sum('discount_amount');
        $totalOutstanding = $contracts->sum(function ($contract) {
            $paid = (float)$contract->allocations->filter(function ($allocation) {
                return $allocation->status === null || $allocation->status === LeasePaymentAllocation::STATUS_ACTIVE;
            })->sum('amount');
            return max(0, (float)$contract->total_amount - (float)($contract->discount_amount ?? 0) - $paid);
        });

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
                return $inst->remaining_amount;
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
                $paid = (float)$contract->allocations->filter(function ($allocation) {
                    return $allocation->status === null || $allocation->status === LeasePaymentAllocation::STATUS_ACTIVE;
                })->sum('amount');
                $bucketAmounts['current'] += max(0, (float)$contract->total_amount - (float)($contract->discount_amount ?? 0) - $paid);
            }
        }

        return [
            'total_contracts' => $contracts->count(),
            'active_contracts' => $contracts->where('status', LeaseContract::STATUS_ACTIVE)->count(),
            'total_contract_value' => $totalValue,
            'total_collected' => $totalCollected,
            'total_discounts' => $totalDiscounts,
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
