<?php

namespace App\Http\Services\Accounting;

use App\Http\Services\AuditService;
use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\AccountingReconciliation;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationService
{
    /**
     * Reconcile store cash register against general ledger Account 1111.
     */
    public function reconcileCash(int $storeId, string $date, float $actualBalance, int $userId, ?string $notes = null): AccountingReconciliation
    {
        return DB::transaction(function () use ($storeId, $date, $actualBalance, $userId, $notes) {
        $dt = Carbon::parse($date);
        $period = $this->lockOpenPeriod($dt);

        // Look up Account 1111 (Tiền mặt quỹ cơ sở)
        $account = AccountingAccount::where('code', '1111')->first();
        $accountId = $account ? $account->id : 0;

        // Calculate book balance up to end of $date
        $debitSum = (float) JournalLine::where('account_id', $accountId)
            ->where('store_id', $storeId)
            ->whereHas('entry', function ($q) use ($date) {
                $q->where('status', 'posted')->where('entry_date', '<=', $date);
            })
            ->sum('debit');

        $creditSum = (float) JournalLine::where('account_id', $accountId)
            ->where('store_id', $storeId)
            ->whereHas('entry', function ($q) use ($date) {
                $q->where('status', 'posted')->where('entry_date', '<=', $date);
            })
            ->sum('credit');

        $bookBalance = round($debitSum - $creditSum, 2);
        $actualBalance = round($actualBalance, 2);
        $difference = round($actualBalance - $bookBalance, 2);
        $status = abs($difference) < 0.01 ? 'matched' : 'discrepancy';

        $rec = AccountingReconciliation::create([
            'period_id' => $period ? $period->id : null,
            'store_id' => $storeId,
            'account_type' => 'cash',
            'reconciliation_date' => $dt->toDateString(),
            'book_balance' => $bookBalance,
            'actual_balance' => $actualBalance,
            'difference' => $difference,
            'status' => $status,
            'reconciled_by' => $userId,
            'reconciled_at' => Carbon::now('Asia/Ho_Chi_Minh'),
            'notes' => $notes,
        ]);

        AuditService::log(
            'accounting.reconciliation.cash',
            $rec,
            null,
            $rec->toArray(),
            "Đối soát két tiền mặt cơ sở {$storeId} ngày {$date}: Chênh lệch {$difference} ({$status})",
            $storeId
        );

        return $rec->fresh(['store', 'period']);
        });
    }

    /**
     * Reconcile bank statement balance against general ledger Account 1121.
     */
    public function reconcileBank(string $date, float $actualBalance, int $userId, ?int $storeId = null, ?string $notes = null): AccountingReconciliation
    {
        return DB::transaction(function () use ($date, $actualBalance, $userId, $storeId, $notes) {
        $dt = Carbon::parse($date);
        $period = $this->lockOpenPeriod($dt);

        // Look up Account 1121 (Tiền gửi NH công ty)
        $account = AccountingAccount::where('code', '1121')->first();
        $accountId = $account ? $account->id : 0;

        $query = JournalLine::where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($date) {
                $q->where('status', 'posted')->where('entry_date', '<=', $date);
            });

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $debitSum = (float) (clone $query)->sum('debit');
        $creditSum = (float) (clone $query)->sum('credit');

        $bookBalance = round($debitSum - $creditSum, 2);
        $actualBalance = round($actualBalance, 2);
        $difference = round($actualBalance - $bookBalance, 2);
        $status = abs($difference) < 0.01 ? 'matched' : 'discrepancy';

        $rec = AccountingReconciliation::create([
            'period_id' => $period ? $period->id : null,
            'store_id' => $storeId,
            'account_type' => 'bank',
            'reconciliation_date' => $dt->toDateString(),
            'book_balance' => $bookBalance,
            'actual_balance' => $actualBalance,
            'difference' => $difference,
            'status' => $status,
            'reconciled_by' => $userId,
            'reconciled_at' => Carbon::now('Asia/Ho_Chi_Minh'),
            'notes' => $notes,
        ]);

        AuditService::log(
            'accounting.reconciliation.bank',
            $rec,
            null,
            $rec->toArray(),
            "Đối soát sao kê ngân hàng ngày {$date}: Chênh lệch {$difference} ({$status})",
            $storeId
        );

        return $rec->fresh(['store', 'period']);
        });
    }

    /**
     * Approve and resolve a reconciliation discrepancy.
     */
    public function approveDiscrepancy(int $recId, string $resolutionNote, int $userId): AccountingReconciliation
    {
        if (trim($resolutionNote) === '') {
            throw ValidationException::withMessages([
                'resolution_note' => 'Bắt buộc phải nhập phương án xử lý chênh lệch để phê duyệt.',
            ]);
        }

        return DB::transaction(function () use ($recId, $resolutionNote, $userId) {
            $candidate = AccountingReconciliation::findOrFail($recId);
            if ($candidate->period_id) {
                $period = AccountingPeriod::where('id', $candidate->period_id)->lockForUpdate()->firstOrFail();
                if ($period->status === 'closed') {
                    throw ValidationException::withMessages([
                        'period' => 'Kỳ kế toán đã khóa; không thể phê duyệt thay đổi đối soát trong kỳ này.',
                    ]);
                }
            }

            $rec = AccountingReconciliation::where('id', $recId)->lockForUpdate()->firstOrFail();
            $before = $rec->toArray();

            $rec->status = 'approved';
            $rec->notes = trim(($rec->notes ? $rec->notes . "\n" : "") . "[Phê duyệt xử lý chênh lệch]: " . $resolutionNote);
            $rec->save();

            AuditService::log(
                'accounting.reconciliation.approve',
                $rec,
                $before,
                $rec->toArray(),
                "Phê duyệt xử lý chênh lệch đối soát ID {$rec->id}: {$resolutionNote}",
                $rec->store_id,
                $userId
            );

            return $rec->fresh(['store', 'period']);
        });
    }

    private function lockOpenPeriod(Carbon $date): AccountingPeriod
    {
        $period = AccountingPeriod::firstOrCreate(
            ['fiscal_year' => $date->year, 'period_month' => $date->month],
            [
                'start_date' => $date->copy()->startOfMonth()->toDateString(),
                'end_date' => $date->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
            ]
        );
        $period = AccountingPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'period' => "Kỳ kế toán tháng {$date->month}/{$date->year} đã khóa; không thể tạo hoặc sửa đối soát.",
            ]);
        }

        return $period;
    }

    /**
     * Generate Trial Balance (Bảng cân đối phát sinh)
     * For each account:
     * - Opening Debit, Opening Credit (before $startDate)
     * - Period Debit, Period Credit (between $startDate and $endDate)
     * - Ending Debit, Ending Credit
     */
    public function getTrialBalance(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $accounts = AccountingAccount::orderBy('code')->get();
        $result = [];

        $totalOpeningDebit = 0;
        $totalOpeningCredit = 0;
        $totalPeriodDebit = 0;
        $totalPeriodCredit = 0;
        $totalEndingDebit = 0;
        $totalEndingCredit = 0;

        foreach ($accounts as $acc) {
            // Opening balance before $startDate
            $openDebit = (float) JournalLine::where('account_id', $acc->id)
                ->when($storeId, function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->whereHas('entry', function ($q) use ($startDate) {
                    $q->where('status', 'posted')->where('entry_date', '<', $startDate);
                })
                ->sum('debit');

            $openCredit = (float) JournalLine::where('account_id', $acc->id)
                ->when($storeId, function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->whereHas('entry', function ($q) use ($startDate) {
                    $q->where('status', 'posted')->where('entry_date', '<', $startDate);
                })
                ->sum('credit');

            $openNet = $acc->normal_balance === 'debit' ? ($openDebit - $openCredit) : ($openCredit - $openDebit);
            $openingDebit = ($acc->normal_balance === 'debit' && $openNet > 0) ? $openNet : 0;
            $openingCredit = ($acc->normal_balance === 'credit' && $openNet > 0) ? $openNet : 0;

            // Period movements
            $periodDebit = (float) JournalLine::where('account_id', $acc->id)
                ->when($storeId, function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->whereHas('entry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted')->whereBetween('entry_date', [$startDate, $endDate]);
                })
                ->sum('debit');

            $periodCredit = (float) JournalLine::where('account_id', $acc->id)
                ->when($storeId, function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->whereHas('entry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted')->whereBetween('entry_date', [$startDate, $endDate]);
                })
                ->sum('credit');

            // Ending balance
            $totalDebitAll = $openDebit + $periodDebit;
            $totalCreditAll = $openCredit + $periodCredit;
            $endNet = $acc->normal_balance === 'debit' ? ($totalDebitAll - $totalCreditAll) : ($totalCreditAll - $totalDebitAll);
            $endingDebit = ($acc->normal_balance === 'debit' && $endNet > 0) ? $endNet : 0;
            $endingCredit = ($acc->normal_balance === 'credit' && $endNet > 0) ? $endNet : 0;

            // Only include if there is any movement or balance
            if ($openingDebit > 0 || $openingCredit > 0 || $periodDebit > 0 || $periodCredit > 0 || $endingDebit > 0 || $endingCredit > 0) {
                $row = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'normal_balance' => $acc->normal_balance,
                    'opening_debit' => round($openingDebit, 2),
                    'opening_credit' => round($openingCredit, 2),
                    'period_debit' => round($periodDebit, 2),
                    'period_credit' => round($periodCredit, 2),
                    'ending_debit' => round($endingDebit, 2),
                    'ending_credit' => round($endingCredit, 2),
                ];
                $result[] = $row;

                $totalOpeningDebit += $row['opening_debit'];
                $totalOpeningCredit += $row['opening_credit'];
                $totalPeriodDebit += $row['period_debit'];
                $totalPeriodCredit += $row['period_credit'];
                $totalEndingDebit += $row['ending_debit'];
                $totalEndingCredit += $row['ending_credit'];
            }
        }

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate, 'store_id' => $storeId],
            'rows' => $result,
            'totals' => [
                'opening_debit' => round($totalOpeningDebit, 2),
                'opening_credit' => round($totalOpeningCredit, 2),
                'period_debit' => round($totalPeriodDebit, 2),
                'period_credit' => round($totalPeriodCredit, 2),
                'ending_debit' => round($totalEndingDebit, 2),
                'ending_credit' => round($totalEndingCredit, 2),
            ],
        ];
    }

    /**
     * Get General Ledger for specific account (Sổ cái chi tiết tài khoản)
     */
    public function getGeneralLedger(int $accountId, string $startDate, string $endDate, ?int $storeId = null): array
    {
        $account = AccountingAccount::findOrFail($accountId);

        // Calculate opening balance before $startDate
        $openDebit = (float) JournalLine::where('account_id', $accountId)
            ->when($storeId, function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->whereHas('entry', function ($q) use ($startDate) {
                $q->where('status', 'posted')->where('entry_date', '<', $startDate);
            })
            ->sum('debit');

        $openCredit = (float) JournalLine::where('account_id', $accountId)
            ->when($storeId, function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->whereHas('entry', function ($q) use ($startDate) {
                $q->where('status', 'posted')->where('entry_date', '<', $startDate);
            })
            ->sum('credit');

        $lines = JournalLine::with(['entry.store'])
            ->where('account_id', $accountId)
            ->when($storeId, function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->whereHas('entry', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'posted')->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->get()
            ->sortBy(function ($line) {
                return $line->entry ? $line->entry->entry_date->timestamp : 0;
            })
            ->values();

        $runningBalance = $account->normal_balance === 'debit' ? ($openDebit - $openCredit) : ($openCredit - $openDebit);
        $movements = [];

        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if ($account->normal_balance === 'debit') {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $movements[] = [
                'id' => $line->id,
                'entry_id' => $line->journal_entry_id,
                'entry_number' => $line->entry ? $line->entry->entry_number : '',
                'entry_date' => $line->entry ? $line->entry->entry_date->toDateString() : '',
                'description' => $line->description ?? ($line->entry ? $line->entry->description : ''),
                'store_id' => $line->store_id,
                'store_name' => $line->entry && $line->entry->store ? $line->entry->store->store_name : null,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => $account,
            'opening_balance' => round($account->normal_balance === 'debit' ? ($openDebit - $openCredit) : ($openCredit - $openDebit), 2),
            'movements' => $movements,
            'closing_balance' => round($runningBalance, 2),
        ];
    }
}
