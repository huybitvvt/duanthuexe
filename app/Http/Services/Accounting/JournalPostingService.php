<?php

namespace App\Http\Services\Accounting;

use App\Http\Services\AuditService;
use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\AccountingVatDocument;
use App\Models\BusinessAsset;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    /**
     * Post a journal entry with explicit lines.
     *
     * Invariants enforced:
     * 1. sum(debit) == sum(credit)
     * 2. At least 2 lines
     * 3. debit >= 0, credit >= 0, line cannot have both > 0
     * 4. Target accounting period must NOT be closed
     * 5. Idempotent by idempotency_key
     */
    public function post(array $data, int $userId): JournalEntry
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = JournalEntry::with('lines.account')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $entryDate = Carbon::parse($data['entry_date'] ?? Carbon::now('Asia/Ho_Chi_Minh'))->toDateString();
        $this->assertPeriodOpen($entryDate);

        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Bút toán kế toán phải có ít nhất 2 dòng định khoản (Nợ / Có).',
            ]);
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $idx => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages([
                    "lines.{$idx}" => 'Số tiền Nợ hoặc Có không được là số âm.',
                ]);
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    "lines.{$idx}" => 'Một dòng định khoản không thể đồng thời ghi Nợ và Có.',
                ]);
            }

            if ($debit == 0 && $credit == 0) {
                throw ValidationException::withMessages([
                    "lines.{$idx}" => 'Dòng định khoản phải có số tiền Nợ hoặc Có lớn hơn 0.',
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'balance' => "Bút toán không cân bằng: Tổng Nợ ({$totalDebit}) != Tổng Có ({$totalCredit}).",
            ]);
        }

        return DB::transaction(function () use ($data, $entryDate, $lines, $userId, $idempotencyKey, $totalDebit) {
            $entryNumber = $this->generateEntryNumber($entryDate);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'store_id' => $data['store_id'] ?? null,
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'status' => 'posted',
                'description' => $data['description'] ?? 'Bút toán kế toán',
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => Carbon::now('Asia/Ho_Chi_Minh'),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'store_id' => $line['store_id'] ?? $entry->store_id,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'description' => $line['description'] ?? $entry->description,
                    'reference_type' => $line['reference_type'] ?? null,
                    'reference_id' => $line['reference_id'] ?? null,
                ]);
            }

            $loaded = $entry->load('lines.account');
            AuditService::log(
                'accounting.journal.post',
                $entry,
                null,
                ['entry_number' => $entryNumber, 'total' => $totalDebit, 'lines_count' => count($lines)],
                "Ghi nhận bút toán {$entryNumber}",
                $entry->store_id
            );

            return $loaded;
        });
    }

    /**
     * Check if the period corresponding to $date is open.
     */
    public function assertPeriodOpen(string $date): void
    {
        $dt = Carbon::parse($date);
        $period = AccountingPeriod::where('fiscal_year', $dt->year)
            ->where('period_month', $dt->month)
            ->first();

        if ($period && $period->status === 'closed') {
            throw ValidationException::withMessages([
                'entry_date' => "Kỳ kế toán tháng {$dt->month}/{$dt->year} đã bị khóa, không thể ghi sổ hoặc hồi tố.",
            ]);
        }
    }

    /**
     * Generate unique entry number JE-YYYYMM-XXXX
     */
    protected function generateEntryNumber(string $date): string
    {
        $prefix = 'JE-' . Carbon::parse($date)->format('Ym') . '-';
        $last = JournalEntry::where('entry_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('entry_number');

        $nextSeq = 1;
        if ($last) {
            $suffix = substr($last, strlen($prefix));
            if (is_numeric($suffix)) {
                $nextSeq = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Lookup account ID by account code
     */
    public function getAccountId(string $code): int
    {
        $acc = AccountingAccount::where('code', $code)->first();
        if (!$acc) {
            throw new \RuntimeException("Không tìm thấy tài khoản kế toán mã {$code}. Vui lòng kiểm tra danh mục tài khoản.");
        }
        return (int) $acc->id;
    }

    /**
     * Helper: Post Cash Receipt (Thu tiền mặt)
     * Nợ 1111 (Tiền mặt tại quỹ cơ sở)
     * Có 5111 / 1311 / 3386
     */
    public function postCashReceipt(int $storeId, string $date, float $amount, string $description, ?int $transactionId, int $userId, ?string $idempotencyKey = null): JournalEntry
    {
        $cashAccId = $this->getAccountId('1111');
        $revenueAccId = $this->getAccountId('5111');

        return $this->post([
            'entry_date' => $date,
            'store_id' => $storeId,
            'source_type' => 'transaction',
            'source_id' => $transactionId,
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
            'lines' => [
                ['account_id' => $cashAccId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $revenueAccId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
        ], $userId);
    }

    /**
     * Helper: Post Bank Receipt (Thu tiền gửi ngân hàng)
     * Nợ 1121 (Tiền gửi ngân hàng công ty)
     * Có 5111
     */
    public function postBankReceipt(int $storeId, string $date, float $amount, string $description, ?int $transactionId, int $userId, ?string $idempotencyKey = null): JournalEntry
    {
        $bankAccId = $this->getAccountId('1121');
        $revenueAccId = $this->getAccountId('5111');

        return $this->post([
            'entry_date' => $date,
            'store_id' => $storeId,
            'source_type' => 'transaction',
            'source_id' => $transactionId,
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
            'lines' => [
                ['account_id' => $bankAccId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $revenueAccId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
        ], $userId);
    }

    /**
     * Helper: Post Lease Installment Payment (Thu tiền kỳ thuê sở hữu)
     * Nợ 1111 / 1121
     * Có 1312 (Phải thu hợp đồng thuê sở hữu)
     */
    public function postLeaseInstallment(int $contractId, int $storeId, string $date, float $amount, string $paymentMethod, string $description, int $userId, ?string $idempotencyKey = null): JournalEntry
    {
        $cashOrBankAccId = ($paymentMethod === 'cash') ? $this->getAccountId('1111') : $this->getAccountId('1121');
        $receivableAccId = $this->getAccountId('1312');

        return $this->post([
            'entry_date' => $date,
            'store_id' => $storeId,
            'source_type' => 'lease_contract',
            'source_id' => $contractId,
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
            'lines' => [
                ['account_id' => $cashOrBankAccId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $receivableAccId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
        ], $userId);
    }

    /**
     * Helper: Post VAT Document
     */
    public function postVatDocument(AccountingVatDocument $doc, int $userId, ?string $idempotencyKey = null): JournalEntry
    {
        $date = $doc->invoice_date->toDateString();
        $storeId = $doc->store_id ?? 1;

        if ($doc->document_type === 'output') {
            // Nợ 1311 / 1111: Tổng tiền
            // Có 5111: Tiền trước thuế
            // Có 3331: Thuế GTGT đầu ra
            $receivableId = $this->getAccountId('1311');
            $revenueId = $this->getAccountId('5111');
            $vatOutputId = $this->getAccountId('3331');

            return $this->post([
                'entry_date' => $date,
                'store_id' => $storeId,
                'source_type' => 'vat_document',
                'source_id' => $doc->id,
                'description' => "Hóa đơn GTGT đầu ra số {$doc->invoice_number} - {$doc->counterparty_name}",
                'idempotency_key' => $idempotencyKey,
                'lines' => [
                    ['account_id' => $receivableId, 'debit' => (float) $doc->total_amount, 'credit' => 0],
                    ['account_id' => $revenueId, 'debit' => 0, 'credit' => (float) $doc->amount_before_tax],
                    ['account_id' => $vatOutputId, 'debit' => 0, 'credit' => (float) $doc->vat_amount],
                ],
            ], $userId);
        } else {
            // Hóa đơn đầu vào
            // Nợ 6422: Tiền trước thuế
            // Nợ 1331: Thuế GTGT đầu vào
            // Có 331 / 1111: Tổng tiền
            $expenseId = $this->getAccountId('6422');
            $vatInputId = $this->getAccountId('1331');
            $payableId = $this->getAccountId('331');

            return $this->post([
                'entry_date' => $date,
                'store_id' => $storeId,
                'source_type' => 'vat_document',
                'source_id' => $doc->id,
                'description' => "Hóa đơn GTGT đầu vào số {$doc->invoice_number} - {$doc->counterparty_name}",
                'idempotency_key' => $idempotencyKey,
                'lines' => [
                    ['account_id' => $expenseId, 'debit' => (float) $doc->amount_before_tax, 'credit' => 0],
                    ['account_id' => $vatInputId, 'debit' => (float) $doc->vat_amount, 'credit' => 0],
                    ['account_id' => $payableId, 'debit' => 0, 'credit' => (float) $doc->total_amount],
                ],
            ], $userId);
        }
    }

    /**
     * Helper: Post Asset Depreciation
     * Nợ 6421 (Chi phí khấu hao TSCĐ)
     * Có 2141 (Hao mòn lũy kế TSCĐ)
     */
    public function postAssetDepreciation(BusinessAsset $asset, string $date, float $amount, int $userId, ?string $idempotencyKey = null): JournalEntry
    {
        $expenseId = $this->getAccountId('6421');
        $deprecAccId = $this->getAccountId('2141');

        return $this->post([
            'entry_date' => $date,
            'store_id' => $asset->store_id,
            'source_type' => 'business_asset',
            'source_id' => $asset->id,
            'description' => "Trích khấu hao tài sản {$asset->asset_code} ({$asset->name})",
            'idempotency_key' => $idempotencyKey,
            'lines' => [
                ['account_id' => $expenseId, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $deprecAccId, 'debit' => 0, 'credit' => $amount],
            ],
        ], $userId);
    }
}
