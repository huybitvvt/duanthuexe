<?php

namespace App\Http\Services\Accounting;

use App\Http\Services\AuditService;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalReversalService
{
    private $postingService;

    public function __construct(JournalPostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Reverse a posted journal entry by creating an opposite offsetting entry.
     *
     * Invariants:
     * 1. Original entry is NOT mutated or deleted; marked status = 'reversed'.
     * 2. New entry created with swapped debit/credit on every line.
     * 3. Reversal date must be in an OPEN accounting period.
     * 4. Cannot reverse an entry that is already reversed.
     */
    public function reverse(int $entryId, string $reason, int $userId, ?string $reversalDate = null): JournalEntry
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Bắt buộc phải nhập lý do đảo bút toán.',
            ]);
        }

        $original = JournalEntry::with('lines')->findOrFail($entryId);

        if ($original->status === 'reversed') {
            throw ValidationException::withMessages([
                'status' => "Bút toán {$original->entry_number} đã được đảo trước đó, không thể đảo lần hai.",
            ]);
        }

        if ($original->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => "Chỉ có thể đảo bút toán đã ghi nhận (posted). Trạng thái hiện tại: {$original->status}.",
            ]);
        }

        $date = $reversalDate ? Carbon::parse($reversalDate)->toDateString() : Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
        $this->postingService->assertPeriodOpen($date);

        return DB::transaction(function () use ($original, $reason, $userId, $date) {
            // Build inverted lines
            $invertedLines = [];
            foreach ($original->lines as $line) {
                $invertedLines[] = [
                    'account_id' => $line->account_id,
                    'store_id' => $line->store_id,
                    'debit' => (float) $line->credit,
                    'credit' => (float) $line->debit,
                    'description' => "[Đảo] " . ($line->description ?? $original->description),
                    'reference_type' => 'reversal_of_line',
                    'reference_id' => $line->id,
                ];
            }

            // Post reversal entry
            $reversalEntry = $this->postingService->post([
                'entry_date' => $date,
                'store_id' => $original->store_id,
                'source_type' => 'reversal',
                'source_id' => $original->id,
                'description' => "Đảo bút toán {$original->entry_number}: {$reason}",
                'lines' => $invertedLines,
            ], $userId);

            // Link reversal to original
            $reversalEntry->reversed_entry_id = $original->id;
            $reversalEntry->save();

            // Update original entry
            $beforeOriginal = $original->toArray();
            $original->status = 'reversed';
            $original->save();

            AuditService::log(
                'accounting.journal.reverse',
                $original,
                $beforeOriginal,
                ['status' => 'reversed', 'reversal_entry_id' => $reversalEntry->id, 'reason' => $reason],
                "Đảo bút toán {$original->entry_number} qua bút toán mới {$reversalEntry->entry_number}",
                $original->store_id
            );

            return $reversalEntry->fresh(['lines.account']);
        });
    }
}
