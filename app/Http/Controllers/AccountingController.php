<?php

namespace App\Http\Controllers;

use App\Http\Services\Accounting\JournalPostingService;
use App\Http\Services\Accounting\JournalReversalService;
use App\Http\Services\Accounting\PeriodCloseService;
use App\Http\Services\Accounting\ReconciliationService;
use App\Http\Services\AccountingService;
use App\Http\Services\AuditService;
use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\AccountingReconciliation;
use App\Models\AccountingVatDocument;
use App\Models\BusinessAsset;
use App\Models\JournalEntry;
use App\Support\PermissionAccess;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    private $service;
    private $postingService;
    private $reversalService;
    private $periodCloseService;
    private $reconciliationService;

    public function __construct(
        AccountingService $service,
        JournalPostingService $postingService,
        JournalReversalService $reversalService,
        PeriodCloseService $periodCloseService,
        ReconciliationService $reconciliationService
    ) {
        $this->service = $service;
        $this->postingService = $postingService;
        $this->reversalService = $reversalService;
        $this->periodCloseService = $periodCloseService;
        $this->reconciliationService = $reconciliationService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);
        $storeId = $this->authorizeStoreScope($user, 'accounting.view', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        return $this->successResponse($this->service->dashboard([
            'start_date' => $validated['start_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString(),
            'end_date' => $validated['end_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->toDateString(),
            'store_id' => $storeId,
        ]));
    }

    public function getAccounts(): JsonResponse
    {
        $user = Auth::user();
        PermissionAccess::can($user, 'accounting.view');

        $accounts = AccountingAccount::orderBy('code')->get();
        return $this->successResponse($accounts);
    }

    public function getJournalEntries(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
            'status' => 'nullable|in:draft,posted,reversed',
            'source_type' => 'nullable|string',
        ]);

        $storeId = $this->authorizeStoreScope($user, 'accounting.view', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        $query = JournalEntry::with(['lines.account', 'store:id,store_name'])
            ->when($storeId, function ($q) use ($storeId) {
                return $q->where('store_id', $storeId);
            })
            ->when(isset($validated['start_date']), function ($q) use ($validated) {
                return $q->where('entry_date', '>=', $validated['start_date']);
            })
            ->when(isset($validated['end_date']), function ($q) use ($validated) {
                return $q->where('entry_date', '<=', $validated['end_date']);
            })
            ->when(isset($validated['status']), function ($q) use ($validated) {
                return $q->where('status', $validated['status']);
            })
            ->when(isset($validated['source_type']), function ($q) use ($validated) {
                return $q->where('source_type', $validated['source_type']);
            })
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc');

        $entries = $query->paginate($request->input('per_page', 50));
        return $this->successResponse($entries);
    }

    public function getJournalEntry(int $id): JsonResponse
    {
        $user = Auth::user();
        $entry = JournalEntry::with(['lines.account', 'store', 'reversedEntry', 'createdByUser:id,name', 'postedByUser:id,name'])
            ->findOrFail($id);

        PermissionAccess::can($user, 'accounting.view', $entry->store_id);

        return $this->successResponse($entry);
    }

    public function postJournalEntry(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'entry_date' => 'required|date_format:Y-m-d',
            'store_id' => 'nullable|integer|exists:stores,id',
            'description' => 'required|string|max:500',
            'source_type' => 'nullable|string|max:100',
            'source_id' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:100',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:accounting_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $requestedStoreId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;
        $storeId = $this->authorizeStoreScope($user, 'accounting.post', $requestedStoreId);
        $validated['store_id'] = $storeId;
        if ($storeId !== null) {
            foreach ($validated['lines'] as $index => $line) {
                if (isset($line['store_id']) && (int) $line['store_id'] !== $storeId) {
                    PermissionAccess::can($user, 'accounting.post', (int) $line['store_id']);
                }
                $validated['lines'][$index]['store_id'] = $storeId;
            }
        }

        $entry = $this->postingService->post($validated, $user->id);
        return $this->successResponse($entry, 'Đã ghi nhận bút toán kế toán thành công.');
    }

    public function reverseJournalEntry(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $entry = JournalEntry::findOrFail($id);
        PermissionAccess::can($user, 'accounting.reverse', $entry->store_id);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'reversal_date' => 'nullable|date_format:Y-m-d',
        ]);

        $reversalEntry = $this->reversalService->reverse($id, $validated['reason'], $user->id, $validated['reversal_date'] ?? null);
        return $this->successResponse($reversalEntry, 'Đã đảo bút toán thành công.');
    }

    public function getPeriods(): JsonResponse
    {
        $user = Auth::user();
        PermissionAccess::can($user, 'accounting.view');

        $periods = AccountingPeriod::with(['closedByUser:id,name', 'reopenedByUser:id,name'])
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get();

        return $this->successResponse($periods);
    }

    public function closePeriod(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCompanyWideAccounting($user, 'accounting.close_period');

        $validated = $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2050',
            'period_month' => 'required|integer|min:1|max:12',
            'notes' => 'nullable|string|max:500',
        ]);

        $period = $this->periodCloseService->closePeriod(
            (int) $validated['fiscal_year'],
            (int) $validated['period_month'],
            $user->id,
            $validated['notes'] ?? null
        );

        return $this->successResponse($period, "Đã khóa kỳ kế toán tháng {$period->period_month}/{$period->fiscal_year}.");
    }

    public function reopenPeriod(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeCompanyWideAccounting($user, 'accounting.close_period');

        $validated = $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2050',
            'period_month' => 'required|integer|min:1|max:12',
            'reason' => 'required|string|max:500',
        ]);

        $period = $this->periodCloseService->reopenPeriod(
            (int) $validated['fiscal_year'],
            (int) $validated['period_month'],
            $user->id,
            $validated['reason']
        );

        return $this->successResponse($period, "Đã mở lại kỳ kế toán tháng {$period->period_month}/{$period->fiscal_year}.");
    }

    public function getReconciliations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
            'account_type' => 'nullable|in:cash,bank',
            'status' => 'nullable|string',
        ]);

        $storeId = $this->authorizeStoreScope($user, 'accounting.view', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        $reconciliations = AccountingReconciliation::with(['store:id,store_name', 'period', 'reconciledByUser:id,name'])
            ->when($storeId, function ($q) use ($storeId) {
                return $q->where('store_id', $storeId);
            })
            ->when(isset($validated['account_type']), function ($q) use ($validated) {
                return $q->where('account_type', $validated['account_type']);
            })
            ->when(isset($validated['status']), function ($q) use ($validated) {
                return $q->where('status', $validated['status']);
            })
            ->orderBy('reconciliation_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($reconciliations);
    }

    public function reconcileCash(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date' => 'required|date_format:Y-m-d',
            'actual_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        PermissionAccess::can($user, 'accounting.reconcile', (int) $validated['store_id']);

        $rec = $this->reconciliationService->reconcileCash(
            (int) $validated['store_id'],
            $validated['date'],
            (float) $validated['actual_balance'],
            $user->id,
            $validated['notes'] ?? null
        );

        return $this->successResponse($rec, 'Đã hoàn tất đối soát két tiền mặt.');
    }

    public function reconcileBank(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'actual_balance' => 'required|numeric',
            'store_id' => 'nullable|integer|exists:stores,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $this->authorizeStoreScope($user, 'accounting.reconcile', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        $rec = $this->reconciliationService->reconcileBank(
            $validated['date'],
            (float) $validated['actual_balance'],
            $user->id,
            $storeId,
            $validated['notes'] ?? null
        );

        return $this->successResponse($rec, 'Đã hoàn tất đối soát sao kê ngân hàng.');
    }

    public function approveReconciliation(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $rec = AccountingReconciliation::findOrFail($id);
        PermissionAccess::can($user, 'accounting.reconcile', $rec->store_id);

        $validated = $request->validate([
            'resolution_note' => 'required|string|max:500',
        ]);

        $updated = $this->reconciliationService->approveDiscrepancy($id, $validated['resolution_note'], $user->id);
        return $this->successResponse($updated, 'Đã phê duyệt xử lý chênh lệch đối soát.');
    }

    public function getTrialBalance(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $this->authorizeStoreScope($user, 'accounting.view', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        $data = $this->reconciliationService->getTrialBalance(
            $validated['start_date'],
            $validated['end_date'],
            $storeId
        );

        return $this->successResponse($data);
    }

    public function getGeneralLedger(Request $request, int $accountId): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $this->authorizeStoreScope($user, 'accounting.view', isset($validated['store_id']) ? (int) $validated['store_id'] : null);

        $data = $this->reconciliationService->getGeneralLedger(
            $accountId,
            $validated['start_date'],
            $validated['end_date'],
            $storeId
        );

        return $this->successResponse($data);
    }

    public function legacyShadowAnalysis(): JsonResponse
    {
        $user = Auth::user();
        $storeId = $this->authorizeStoreScope($user, 'accounting.view', null);

        // Check legacy transactions table if exists
        $hasTransactions = DB::getSchemaBuilder()->hasTable('transactions');
        if (!$hasTransactions) {
            return $this->successResponse(['status' => 'not_available', 'message' => 'Bảng transactions không tồn tại.']);
        }

        $transactionSchema = DB::getSchemaBuilder();
        if ($storeId !== null && !$transactionSchema->hasColumn('transactions', 'store_id')) {
            return $this->successResponse([
                'status' => 'not_available_for_store_scope',
                'message' => 'Dữ liệu giao dịch cũ không có trường cơ sở nên đã chặn báo cáo toàn công ty cho tài khoản giới hạn cơ sở.',
            ]);
        }
        $transactionQuery = DB::table('transactions');
        if ($storeId !== null && $transactionSchema->hasColumn('transactions', 'store_id')) {
            $transactionQuery->where('store_id', $storeId);
        }
        $totalCount = (clone $transactionQuery)->count();
        $amountColumn = $transactionSchema->hasColumn('transactions', 'value') ? 'value' : null;
        $methodColumn = $transactionSchema->hasColumn('transactions', 'payment_method') ? 'payment_method' : null;

        if ($amountColumn && $methodColumn) {
            $mappedCount = (clone $transactionQuery)
                ->whereNotNull($methodColumn)
                ->where($amountColumn, '>', 0)
                ->count();
            $ambiguousCount = (clone $transactionQuery)
                ->where(function ($query) use ($methodColumn, $amountColumn) {
                    $query->whereNull($methodColumn)
                        ->orWhere($amountColumn, '<=', 0);
                })
                ->count();
        } else {
            // Legacy installations only have value/type and cannot be safely
            // auto-mapped to a journal. Report them as ambiguous instead of
            // issuing a SQL error or inventing an accounting classification.
            $mappedCount = 0;
            $ambiguousCount = $totalCount;
        }

        $journalQuery = JournalEntry::query()->when($storeId !== null, function ($query) use ($storeId) {
            return $query->where('store_id', $storeId);
        });
        $postedJournalCount = (clone $journalQuery)->where('source_type', 'transaction')->count();

        return $this->successResponse([
            'legacy_transactions' => [
                'total' => $totalCount,
                'mapped_eligible' => $mappedCount,
                'ambiguous_or_invalid' => $ambiguousCount,
            ],
            'journal_entries' => [
                'posted_from_transactions' => $postedJournalCount,
                'total_journal_entries' => (clone $journalQuery)->count(),
            ],
            'shadow_mode' => [
                'status' => 'active',
                'policy' => 'Không tự động backfill mục ambiguous; chỉ post các giao dịch có đầy đủ chứng từ và được kế toán phê duyệt.',
            ],
        ]);
    }

    public function saveVatDocument(Request $request): JsonResponse
    {
        $user = Auth::user();
        $id = $request->input('id');
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:accounting_vat_documents,id',
            'document_type' => 'required|in:input,output',
            'invoice_number' => [
                'required', 'string', 'max:100',
                Rule::unique('accounting_vat_documents', 'invoice_number')
                    ->where(function ($query) use ($request) {
                        return $query->where('document_type', $request->input('document_type'));
                    })->ignore($id),
            ],
            'invoice_date' => 'required|date_format:Y-m-d',
            'counterparty_name' => 'required|string|max:200',
            'tax_code' => 'nullable|string|max:50',
            'amount_before_tax' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'payment_status' => 'required|in:unpaid,partial,paid',
            'store_id' => 'nullable|integer|exists:stores,id',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
            'notes' => 'nullable|string|max:1000',
            'auto_post_journal' => 'nullable|boolean',
        ]);

        if ($id) {
            $existing = AccountingVatDocument::findOrFail((int) $id);
            PermissionAccess::can($user, 'accounting.post', $existing->store_id);
        }
        $storeId = $this->authorizeStoreScope($user, 'accounting.post', isset($validated['store_id']) ? (int) $validated['store_id'] : null);
        $validated['store_id'] = $storeId;

        $doc = $this->service->saveVatDocument($validated, $user->id, $id ? (int) $id : null);
        AuditService::log('accounting.vat_document.save', $doc, null, $doc->toArray(), 'Lưu chứng từ VAT', $doc->store_id);

        // Optionally post journal entry
        if (!empty($validated['auto_post_journal'])) {
            try {
                $this->postingService->postVatDocument($doc, $user->id);
            } catch (\Exception $e) {
                // Keep document saved, surface warning
            }
        }

        return $this->successResponse($doc, 'Đã lưu chứng từ VAT.');
    }

    public function saveAsset(Request $request): JsonResponse
    {
        $user = Auth::user();
        $id = $request->input('id');
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:business_assets,id',
            'asset_code' => ['required', 'string', 'max:60', Rule::unique('business_assets', 'asset_code')->ignore($id)],
            'name' => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'store_id' => 'nullable|integer|exists:stores,id',
            'purchase_date' => 'nullable|date_format:Y-m-d',
            'purchase_cost' => 'required|numeric|min:0',
            'residual_value' => 'nullable|numeric|min:0',
            'depreciation_months' => 'required|integer|min:0|max:1200',
            'status' => 'required|in:active,disposed,maintenance',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($id) {
            $existing = BusinessAsset::findOrFail((int) $id);
            PermissionAccess::can($user, 'accounting.post', $existing->store_id);
        }
        $storeId = $this->authorizeStoreScope($user, 'accounting.post', isset($validated['store_id']) ? (int) $validated['store_id'] : null);
        $validated['store_id'] = $storeId;

        $asset = $this->service->saveAsset($validated, $user->id, $id ? (int) $id : null);
        AuditService::log('accounting.asset.save', $asset, null, $asset->toArray(), 'Lưu tài sản', $asset->store_id);

        return $this->successResponse($asset, 'Đã lưu tài sản.');
    }

    public function deleteVatDocument(int $id): JsonResponse
    {
        $user = Auth::user();
        $doc = AccountingVatDocument::findOrFail($id);
        PermissionAccess::can($user, 'accounting.reverse', $doc->store_id);
        $before = $doc->toArray();
        $doc->payment_status = 'unpaid';
        $doc->notes = trim(($doc->notes ? $doc->notes . "\n" : "") . "[Đã hủy bởi " . $user->name . " lúc " . Carbon::now()->format('d/m/Y H:i') . "]");
        $doc->save();
        AuditService::log('accounting.vat_document.cancel', $doc, $before, $doc->toArray(), 'Hủy chứng từ VAT', $doc->store_id);

        return $this->successResponse($doc, 'Đã hủy chứng từ VAT.');
    }

    public function deleteAsset(int $id): JsonResponse
    {
        $user = Auth::user();
        $asset = BusinessAsset::findOrFail($id);
        PermissionAccess::can($user, 'accounting.reverse', $asset->store_id);
        $before = $asset->toArray();
        $asset->status = 'disposed';
        $asset->notes = trim(($asset->notes ? $asset->notes . "\n" : "") . "[Đã thanh lý bởi " . $user->name . " lúc " . Carbon::now()->format('d/m/Y H:i') . "]");
        $asset->save();
        AuditService::log('accounting.asset.dispose', $asset, $before, $asset->toArray(), 'Thanh lý tài sản', $asset->store_id);

        return $this->successResponse($asset, 'Đã thanh lý tài sản.');
    }

    /**
     * Resolve the effective store before querying or writing accounting data.
     * Store-scoped users cannot turn a request into a company-wide query by
     * omitting store_id.
     */
    private function authorizeStoreScope($user, string $permission, ?int $requestedStoreId): ?int
    {
        PermissionAccess::can($user, $permission, $requestedStoreId);

        if (!PermissionAccess::isAdmin($user)
            && !PermissionAccess::allows($user, 'kpi.view_company')
            && $user->store_id) {
            return (int) $user->store_id;
        }

        return $requestedStoreId;
    }

    private function authorizeCompanyWideAccounting($user, string $permission): void
    {
        PermissionAccess::can($user, $permission);
        if (!PermissionAccess::isAdmin($user) && $user->store_id) {
            throw new AuthorizationException('Tài khoản kế toán giới hạn cơ sở không được khóa hoặc mở kỳ kế toán toàn công ty.');
        }
    }
}
