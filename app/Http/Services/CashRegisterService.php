<?php

namespace App\Http\Services;

use App\Models\DailyCashRegister;
use App\Models\Order;
use App\Models\Store;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashRegisterService
{
    /**
     * Lấy tóm tắt số liệu thu chi trong ngày (cho 1 cơ sở hoặc toàn hệ thống).
     *
     * @param int|null $storeId
     * @param string|Carbon $date
     * @param int|null $userId
     * @return array
     */
    public function getDailySummary(?int $storeId, $date, ?int $userId = null): array
    {
        $parsedDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $parsedDate->format('Y-m-d');

        // Xác định số dư đầu ngày và trạng thái két đã lưu nếu có
        $openingBalance = 0;
        $register = null;

        if ($storeId) {
            $register = DailyCashRegister::where('register_date', $dateStr)
                ->where('store_id', $storeId)
                ->first();

            if ($register && $register->opening_balance > 0) {
                $openingBalance = (float) $register->opening_balance;
            } else {
                $previousClosed = DailyCashRegister::where('register_date', '<', $dateStr)
                    ->where('store_id', $storeId)
                    ->where('status', 'closed')
                    ->orderBy('register_date', 'desc')
                    ->first();

                if ($previousClosed) {
                    $openingBalance = (float) ($previousClosed->actual_cash_counted ?? $previousClosed->system_cash_balance);
                }
            }
        } else {
            // Tổng hợp toàn hệ thống: tính tổng số dư đầu ngày từ các cơ sở vật lý
            $physicalStores = Store::where('kind', 'physical')->get();
            if ($physicalStores->isEmpty()) {
                $physicalStores = Store::all();
            }

            foreach ($physicalStores as $st) {
                $stReg = DailyCashRegister::where('register_date', $dateStr)
                    ->where('store_id', $st->id)
                    ->first();

                if ($stReg && $stReg->opening_balance > 0) {
                    $openingBalance += (float) $stReg->opening_balance;
                } else {
                    $prev = DailyCashRegister::where('register_date', '<', $dateStr)
                        ->where('store_id', $st->id)
                        ->where('status', 'closed')
                        ->orderBy('register_date', 'desc')
                        ->first();

                    if ($prev) {
                        $openingBalance += (float) ($prev->actual_cash_counted ?? $prev->system_cash_balance);
                    }
                }
            }
            // Toàn hệ thống không dùng snapshot đơn lẻ của 1 cơ sở
            $register = null;
        }

        // Nếu cơ sở cụ thể đã chốt két, trả về dữ liệu cố định trong bản ghi
        if ($register && $register->status === 'closed') {
            return [
                'id' => $register->id,
                'store_id' => $register->store_id,
                'store_name' => $register->store ? $register->store->store_name : 'Cơ sở #' . $register->store_id,
                'register_date' => $dateStr,
                'status' => 'closed',
                'opening_balance' => (float) $register->opening_balance,
                'total_orders_count' => (int) $register->total_orders_count,
                // Cash
                'deposit_cash' => (float) $register->deposit_cash,
                'rental_cash' => (float) $register->rental_cash,
                'renewal_cash' => (float) $register->renewal_cash,
                'refund_deposit_cash' => (float) $register->refund_deposit_cash,
                'penalty_cash' => (float) $register->penalty_cash,
                'other_income_cash' => (float) $register->other_income_cash,
                'other_expense_cash' => (float) $register->other_expense_cash,
                // Bank Personal
                'deposit_bank_personal' => (float) $register->deposit_bank_personal,
                'rental_bank_personal' => (float) $register->rental_bank_personal,
                'renewal_bank_personal' => (float) $register->renewal_bank_personal,
                'refund_deposit_bank_personal' => (float) $register->refund_deposit_bank_personal,
                'penalty_bank_personal' => (float) $register->penalty_bank_personal,
                'other_expense_bank_personal' => (float) ($register->other_expense_bank_personal ?? 0),
                // Bank Company
                'deposit_bank_company' => (float) $register->deposit_bank_company,
                'rental_bank_company' => (float) $register->rental_bank_company,
                'renewal_bank_company' => (float) $register->renewal_bank_company,
                'refund_deposit_bank_company' => (float) $register->refund_deposit_bank_company,
                'penalty_bank_company' => (float) $register->penalty_bank_company,
                'other_expense_bank_company' => (float) ($register->other_expense_bank_company ?? 0),
                // Aggregates
                'total_cash_in' => (float) ($register->deposit_cash + $register->rental_cash + $register->renewal_cash + $register->penalty_cash + $register->other_income_cash),
                'total_cash_out' => (float) ($register->refund_deposit_cash + $register->other_expense_cash),
                'total_bank_personal' => (float) ($register->deposit_bank_personal + $register->rental_bank_personal + $register->renewal_bank_personal + $register->penalty_bank_personal - $register->refund_deposit_bank_personal - ($register->other_expense_bank_personal ?? 0)),
                'total_bank_company' => (float) ($register->deposit_bank_company + $register->rental_bank_company + $register->renewal_bank_company + $register->penalty_bank_company - $register->refund_deposit_bank_company - ($register->other_expense_bank_company ?? 0)),
                'system_cash_balance' => (float) $register->system_cash_balance,
                'actual_cash_counted' => (float) $register->actual_cash_counted,
                'cash_difference' => (float) $register->cash_difference,
                'difference_reason' => $register->difference_reason,
                'closed_by' => $register->closed_by,
                'closed_by_name' => $register->closedByUser ? $register->closedByUser->name : null,
                'closed_at' => $register->closed_at ? $register->closed_at->toDateTimeString() : null,
                'notes' => $register->notes,
            ];
        }

        // Két đang mở -> Tổng hợp thời gian thực từ Orders và Transactions
        $orderQuery = Order::query()
            ->whereDate('created_at', $dateStr)
            ->when($storeId, function ($q) use ($storeId) {
                return $q->where('store_id', $storeId);
            });

        $totalOrdersCount = $orderQuery->count();

        // Tổng hợp từ bảng Transactions trong ngày
        $transQuery = Transaction::query()
            ->whereDate('created_at', $dateStr)
            ->when($storeId, function ($q) use ($storeId) {
                return $q->where('store_id', $storeId);
            });

        $transactions = $transQuery->get();

        $depositCash = 0;
        $rentalCash = 0;
        $renewalCash = 0;
        $refundDepositCash = 0;
        $penaltyCash = 0;
        $otherIncomeCash = 0;
        $otherExpenseCash = 0;

        $depositBankPersonal = 0;
        $rentalBankPersonal = 0;
        $renewalBankPersonal = 0;
        $refundDepositBankPersonal = 0;
        $penaltyBankPersonal = 0;
        $otherExpenseBankPersonal = 0;

        $depositBankCompany = 0;
        $rentalBankCompany = 0;
        $renewalBankCompany = 0;
        $refundDepositBankCompany = 0;
        $penaltyBankCompany = 0;
        $otherExpenseBankCompany = 0;

        foreach ($transactions as $t) {
            $val = (float) $t->value;
            $isCash = ((int) $t->payment_method === 1 || empty($t->payment_method));
            $isPersonal = ($t->bank_owner_type === 'personal');
            $isCompany = ($t->bank_owner_type === 'company');
            $name = mb_strtolower($t->name ?? '');
            $desc = mb_strtolower($t->desc ?? '');
            $combinedText = $name . ' ' . $desc;

            if ($t->type === Transaction::THU) {
                if (str_contains($combinedText, 'cọc') || str_contains($combinedText, 'deposit')) {
                    if ($isCash) $depositCash += $val;
                    elseif ($isCompany) $depositBankCompany += $val;
                    else $depositBankPersonal += $val;
                } elseif (str_contains($combinedText, 'gia hạn') || str_contains($combinedText, 'renewal')) {
                    if ($isCash) $renewalCash += $val;
                    elseif ($isCompany) $renewalBankCompany += $val;
                    else $renewalBankPersonal += $val;
                } elseif (str_contains($combinedText, 'phạt') || str_contains($combinedText, 'penalty')) {
                    if ($isCash) $penaltyCash += $val;
                    elseif ($isCompany) $penaltyBankCompany += $val;
                    else $penaltyBankPersonal += $val;
                } elseif (str_contains($combinedText, 'thuê') || str_contains($combinedText, 'rent')) {
                    if ($isCash) $rentalCash += $val;
                    elseif ($isCompany) $rentalBankCompany += $val;
                    else $rentalBankPersonal += $val;
                } else {
                    if ($isCash) $otherIncomeCash += $val;
                    elseif ($isCompany) $rentalBankCompany += $val;
                    else $rentalBankPersonal += $val;
                }
            } elseif ($t->type === Transaction::CHI) {
                if (str_contains($combinedText, 'hoàn cọc') || str_contains($combinedText, 'refund')) {
                    if ($isCash) $refundDepositCash += $val;
                    elseif ($isCompany) $refundDepositBankCompany += $val;
                    else $refundDepositBankPersonal += $val;
                } else {
                    // Chi vận hành, sửa xe, đảo thu, chi phí khác
                    if ($isCash) {
                        $otherExpenseCash += $val;
                    } elseif ($isCompany) {
                        $otherExpenseBankCompany += $val;
                    } else {
                        $otherExpenseBankPersonal += $val;
                    }
                }
            }
        }

        // Dự tính tiền mặt tồn két = Số dư đầu ngày + Thu tiền mặt - Chi/Hoàn tiền mặt
        $totalCashIn = $depositCash + $rentalCash + $renewalCash + $penaltyCash + $otherIncomeCash;
        $totalCashOut = $refundDepositCash + $otherExpenseCash;
        $systemCashBalance = $openingBalance + $totalCashIn - $totalCashOut;

        $totalBankPersonal = $depositBankPersonal + $rentalBankPersonal + $renewalBankPersonal + $penaltyBankPersonal - $refundDepositBankPersonal - $otherExpenseBankPersonal;
        $totalBankCompany = $depositBankCompany + $rentalBankCompany + $renewalBankCompany + $penaltyBankCompany - $refundDepositBankCompany - $otherExpenseBankCompany;

        $store = $storeId ? Store::find($storeId) : null;

        return [
            'id' => null,
            'store_id' => $storeId,
            'store_name' => $store ? $store->store_name : 'Toàn hệ thống (Tất cả cơ sở)',
            'register_date' => $dateStr,
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'total_orders_count' => $totalOrdersCount,
            // Cash breakdown
            'deposit_cash' => $depositCash,
            'rental_cash' => $rentalCash,
            'renewal_cash' => $renewalCash,
            'refund_deposit_cash' => $refundDepositCash,
            'penalty_cash' => $penaltyCash,
            'other_income_cash' => $otherIncomeCash,
            'other_expense_cash' => $otherExpenseCash,
            // Bank personal breakdown
            'deposit_bank_personal' => $depositBankPersonal,
            'rental_bank_personal' => $rentalBankPersonal,
            'renewal_bank_personal' => $renewalBankPersonal,
            'refund_deposit_bank_personal' => $refundDepositBankPersonal,
            'penalty_bank_personal' => $penaltyBankPersonal,
            'other_expense_bank_personal' => $otherExpenseBankPersonal,
            // Bank company breakdown
            'deposit_bank_company' => $depositBankCompany,
            'rental_bank_company' => $rentalBankCompany,
            'renewal_bank_company' => $renewalBankCompany,
            'refund_deposit_bank_company' => $refundDepositBankCompany,
            'penalty_bank_company' => $penaltyBankCompany,
            'other_expense_bank_company' => $otherExpenseBankCompany,
            // Combined totals
            'total_cash_in' => $totalCashIn,
            'total_cash_out' => $totalCashOut,
            'total_bank_personal' => $totalBankPersonal,
            'total_bank_company' => $totalBankCompany,
            'system_cash_balance' => $systemCashBalance,
            'actual_cash_counted' => null,
            'cash_difference' => 0,
            'difference_reason' => null,
            'closed_by' => null,
            'closed_by_name' => null,
            'closed_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Chốt két ngày (Admin hoặc Kế toán).
     *
     * @param int $storeId
     * @param string|Carbon $date
     * @param float $actualCashCounted
     * @param string|null $differenceReason
     * @param int $userId
     * @param string|null $notes
     * @return DailyCashRegister
     * @throws \Exception
     */
    public function closeDailyRegister(
        int $storeId,
        $date,
        float $actualCashCounted,
        ?string $differenceReason,
        int $userId,
        ?string $notes = null
    ): DailyCashRegister {
        $parsedDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $parsedDate->format('Y-m-d');

        return DB::transaction(function () use (
            $storeId,
            $dateStr,
            $actualCashCounted,
            $differenceReason,
            $userId,
            $notes
        ) {
            $existing = DailyCashRegister::where('store_id', $storeId)
                ->where('register_date', $dateStr)
                ->lockForUpdate()
                ->first();

            // Chặn ghi đè sổ két đã đóng
            if ($existing && $existing->status === 'closed') {
                throw new \Exception("Sổ két ngày {$dateStr} của cơ sở này đã được chốt trước đó. Không thể ghi đè. Vui lòng liên hệ Quản trị viên mở lại két trước khi chốt lại.");
            }

            // Tính toán snapshot số liệu thực tế tại thời điểm chốt
            $summary = $this->getDailySummary($storeId, $dateStr, $userId);

            $systemCashBalance = (float) $summary['system_cash_balance'];
            $cashDifference = $actualCashCounted - $systemCashBalance;

            if (abs($cashDifference) > 0.01 && empty(trim($differenceReason ?? ''))) {
                throw new \Exception("Có chênh lệch tiền mặt (" . number_format($cashDifference, 0, ',', '.') . " đ). Vui lòng nhập lý do chênh lệch.");
            }

            $payload = [
                'opening_balance' => $summary['opening_balance'],
                'total_orders_count' => $summary['total_orders_count'],
                'deposit_cash' => $summary['deposit_cash'],
                'rental_cash' => $summary['rental_cash'],
                'renewal_cash' => $summary['renewal_cash'],
                'refund_deposit_cash' => $summary['refund_deposit_cash'],
                'penalty_cash' => $summary['penalty_cash'],
                'other_income_cash' => $summary['other_income_cash'],
                'other_expense_cash' => $summary['other_expense_cash'],
                'deposit_bank_personal' => $summary['deposit_bank_personal'],
                'rental_bank_personal' => $summary['rental_bank_personal'],
                'renewal_bank_personal' => $summary['renewal_bank_personal'],
                'refund_deposit_bank_personal' => $summary['refund_deposit_bank_personal'],
                'penalty_bank_personal' => $summary['penalty_bank_personal'],
                'other_expense_bank_personal' => $summary['other_expense_bank_personal'] ?? 0,
                'deposit_bank_company' => $summary['deposit_bank_company'],
                'rental_bank_company' => $summary['rental_bank_company'],
                'renewal_bank_company' => $summary['renewal_bank_company'],
                'refund_deposit_bank_company' => $summary['refund_deposit_bank_company'],
                'penalty_bank_company' => $summary['penalty_bank_company'],
                'other_expense_bank_company' => $summary['other_expense_bank_company'] ?? 0,
                'system_cash_balance' => $systemCashBalance,
                'actual_cash_counted' => $actualCashCounted,
                'cash_difference' => $cashDifference,
                'difference_reason' => $differenceReason,
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => Carbon::now(),
                'notes' => $notes,
            ];

            if ($existing) {
                $existing->update($payload);
                $register = $existing;
            } else {
                $register = DailyCashRegister::create(array_merge([
                    'store_id' => $storeId,
                    'register_date' => $dateStr,
                ], $payload));
            }

            Log::info("Daily cash register closed for store #{$storeId} on {$dateStr} by user #{$userId}. System: {$systemCashBalance}, Counted: {$actualCashCounted}, Diff: {$cashDifference}");

            return $register;
        });
    }

    /**
     * Mở lại sổ két (Admin only).
     *
     * @param int $storeId
     * @param string|Carbon $date
     * @param int $userId
     * @return DailyCashRegister
     * @throws \Exception
     */
    public function reopenDailyRegister(int $storeId, $date, int $userId): DailyCashRegister
    {
        $parsedDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $parsedDate->format('Y-m-d');

        $register = DailyCashRegister::where('store_id', $storeId)
            ->where('register_date', $dateStr)
            ->firstOrFail();

        $register->update([
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
            'notes' => ($register->notes ? $register->notes . " | " : "") . "Được mở lại bởi User #{$userId} lúc " . Carbon::now()->toDateTimeString(),
        ]);

        return $register;
    }
}
