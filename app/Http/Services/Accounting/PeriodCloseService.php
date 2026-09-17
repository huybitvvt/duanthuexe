<?php

namespace App\Http\Services\Accounting;

use App\Http\Services\AuditService;
use App\Models\AccountingPeriod;
use App\Models\AccountingReconciliation;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodCloseService
{
    /**
     * Close an accounting period.
     *
     * Pre-checks:
     * 1. Cannot re-close an already closed period.
     * 2. Cannot close if there are unresolved draft journal entries in the period.
     * 3. Cannot close if there are pending or unapproved discrepancies in reconciliations.
     */
    public function closePeriod(int $year, int $month, int $userId, ?string $notes = null): AccountingPeriod
    {
        return DB::transaction(function () use ($year, $month, $userId, $notes) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

            $period = AccountingPeriod::where('fiscal_year', $year)
                ->where('period_month', $month)
                ->lockForUpdate()
                ->first();

            if (!$period) {
                $period = AccountingPeriod::create([
                    'fiscal_year' => $year,
                    'period_month' => $month,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'open',
                ]);
            }

            if ($period->status === 'closed') {
                throw ValidationException::withMessages([
                    'period' => "Kỳ kế toán tháng {$month}/{$year} đã được đóng trước đó vào lúc " . ($period->closed_at ? $period->closed_at->format('d/m/Y H:i') : '') . ".",
                ]);
            }

            // Check 1: Any draft journal entries
            $draftEntriesCount = JournalEntry::whereBetween('entry_date', [$startDate, $endDate])
                ->where('status', 'draft')
                ->lockForUpdate()
                ->count();

            if ($draftEntriesCount > 0) {
                throw ValidationException::withMessages([
                    'draft_entries' => "Không thể đóng kỳ: Vẫn còn {$draftEntriesCount} bút toán nháp (draft) chưa ghi sổ trong tháng {$month}/{$year}.",
                ]);
            }

            // Check 2: Any unapproved discrepancies
            $discrepanciesCount = AccountingReconciliation::where('period_id', $period->id)
                ->where('status', 'discrepancy')
                ->lockForUpdate()
                ->count();

            if ($discrepanciesCount > 0) {
                throw ValidationException::withMessages([
                    'reconciliations' => "Không thể đóng kỳ: Vẫn còn {$discrepanciesCount} biên bản đối soát có chênh lệch chưa được phê duyệt/xử lý.",
                ]);
            }

            $before = $period->toArray();
            $period->status = 'closed';
            $period->closed_at = Carbon::now('Asia/Ho_Chi_Minh');
            $period->closed_by = $userId;
            if ($notes) {
                $period->notes = trim(($period->notes ? $period->notes . "\n" : "") . "[Đóng kỳ]: " . $notes);
            }
            $period->save();

            AuditService::log(
                'accounting.period.close',
                $period,
                $before,
                $period->toArray(),
                "Khóa sổ kỳ kế toán tháng {$month}/{$year}"
            );

            return $period;
        });
    }

    /**
     * Reopen a closed accounting period.
     * Requires explicit reason and appropriate capability.
     */
    public function reopenPeriod(int $year, int $month, int $userId, string $reason): AccountingPeriod
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Bắt buộc phải nhập lý do mở lại kỳ kế toán đã khóa.',
            ]);
        }

        $period = AccountingPeriod::where('fiscal_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$period || $period->status !== 'closed') {
            throw ValidationException::withMessages([
                'period' => "Kỳ kế toán tháng {$month}/{$year} hiện không ở trạng thái khóa.",
            ]);
        }

        $before = $period->toArray();
        $period->status = 'open';
        $period->reopened_at = Carbon::now('Asia/Ho_Chi_Minh');
        $period->reopened_by = $userId;
        $period->notes = trim(($period->notes ? $period->notes . "\n" : "") . "[Mở lại kỳ]: " . $reason);
        $period->save();

        AuditService::log(
            'accounting.period.reopen',
            $period,
            $before,
            $period->toArray(),
            "Mở lại kỳ kế toán tháng {$month}/{$year} - Lý do: {$reason}"
        );

        return $period;
    }
}
