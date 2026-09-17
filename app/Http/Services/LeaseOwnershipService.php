<?php

namespace App\Http\Services;

use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeaseOwnershipEvent;
use App\Models\LeaseOwnershipRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Support\PermissionAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaseOwnershipService
{
    /**
     * Create draft ownership transfer request.
     */
    public function createDraft(int $contractId, User $actor, array $checklist = []): LeaseOwnershipRequest
    {
        $contractForAuthorization = LeaseContract::findOrFail($contractId);
        PermissionAccess::can($actor, 'lease.ownership_request', $contractForAuthorization->store_id);

        return DB::transaction(function () use ($contractId, $actor, $checklist) {
            $contract = LeaseContract::where('id', $contractId)->lockForUpdate()->firstOrFail();

            $activeRequest = LeaseOwnershipRequest::where('lease_contract_id', $contract->id)
                ->whereIn('status', [
                    LeaseOwnershipRequest::STATUS_DRAFT,
                    LeaseOwnershipRequest::STATUS_SUBMITTED,
                    LeaseOwnershipRequest::STATUS_APPROVED,
                ])
                ->first();

            if ($activeRequest) {
                throw ValidationException::withMessages([
                    'ownership_request' => 'Hợp đồng đã có một hồ sơ chuyển quyền đang xử lý.',
                ]);
            }

            // deposit_amount is a contractual obligation, not proof of payment.
            // Only active allocations reduce the debt.
            $activePaid = (float) $contract->allocations()->effectivePayments()->sum('amount');
            $discount = (float) ($contract->discount_amount ?? 0);
            $remainingDebt = max(0, (float) $contract->total_amount - $activePaid - $discount);
            $unpaidCount = $contract->installments()->where('status', '!=', LeaseInstallment::STATUS_PAID)->count();

            $request = LeaseOwnershipRequest::create([
                'lease_contract_id' => $contract->id,
                'vehicle_id' => $contract->vehicle_id,
                'customer_id' => $contract->customer_id,
                'store_id' => $contract->store_id,
                'status' => LeaseOwnershipRequest::STATUS_DRAFT,
                'total_contract_amount' => (float) $contract->total_amount,
                'total_paid_amount' => $activePaid,
                'discount_amount' => $discount,
                'remaining_debt' => $remainingDebt,
                'unpaid_installments_count' => $unpaidCount,
                'checklist_documents' => $checklist,
                'requested_by' => $actor->id,
            ]);

            LeaseOwnershipEvent::create([
                'ownership_request_id' => $request->id,
                'from_status' => null,
                'to_status' => LeaseOwnershipRequest::STATUS_DRAFT,
                'actor_user_id' => $actor->id,
                'notes' => 'Khởi tạo hồ sơ đề nghị chuyển quyền sở hữu xe.',
            ]);

            AuditService::log('lease.ownership.create_draft', $request, null, $request->toArray(), 'Tạo hồ sơ chuyển quyền sở hữu', $contract->store_id);

            return $request;
        });
    }

    /**
     * Submit request for maker-checker approval.
     */
    public function submit(int $requestId, User $actor, ?string $notes = null): LeaseOwnershipRequest
    {
        $request = LeaseOwnershipRequest::with('contract')->findOrFail($requestId);
        PermissionAccess::can($actor, 'lease.ownership_request', $request->store_id);

        if ($request->status !== LeaseOwnershipRequest::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hồ sơ ở trạng thái nháp (draft) mới có thể gửi phê duyệt.'
            ]);
        }

        // Re-verify debt
        $contract = $request->contract;
        $activePaid = (float) $contract->allocations()->effectivePayments()->sum('amount');
        $discount = (float) ($contract->discount_amount ?? 0);
        $remainingDebt = max(0, (float) $contract->total_amount - $activePaid - $discount);

        if ($remainingDebt > 0.01) {
            throw ValidationException::withMessages([
                'debt' => 'Không thể gửi duyệt chuyển quyền khi hợp đồng còn nợ (' . number_format($remainingDebt, 0, ',', '.') . ' đ).'
            ]);
        }

        $request->status = LeaseOwnershipRequest::STATUS_SUBMITTED;
        $request->submitted_at = Carbon::now();
        $request->requested_by = $actor->id;
        $request->remaining_debt = 0;
        $request->save();

        LeaseOwnershipEvent::create([
            'ownership_request_id' => $request->id,
            'from_status' => LeaseOwnershipRequest::STATUS_DRAFT,
            'to_status' => LeaseOwnershipRequest::STATUS_SUBMITTED,
            'actor_user_id' => $actor->id,
            'notes' => $notes ?: 'Gửi duyệt chuyển quyền sở hữu xe lên cấp có thẩm quyền.',
        ]);

        AuditService::log('lease.ownership.submit', $request, null, $request->toArray(), 'Gửi duyệt chuyển quyền sở hữu', $request->store_id);

        return $request;
    }

    public function submitForApproval(int $requestId, User $actor, ?string $notes = null): LeaseOwnershipRequest
    {
        return $this->submit($requestId, $actor, $notes);
    }

    /**
     * Approve ownership transfer request.
     */
    public function approve(int $requestId, User $actor, string $reason): LeaseOwnershipRequest
    {
        $request = LeaseOwnershipRequest::findOrFail($requestId);
        PermissionAccess::can($actor, 'lease.ownership_approve', $request->store_id);

        if ($request->status !== LeaseOwnershipRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hồ sơ ở trạng thái chờ duyệt (submitted) mới có thể phê duyệt.'
            ]);
        }

        // Maker-checker rule: Người duyệt không được là người tạo yêu cầu
        if ((int) $actor->id === (int) $request->requested_by) {
            throw ValidationException::withMessages([
                'approver' => 'Quy tắc Maker-Checker: Người duyệt không được trùng với người khởi tạo yêu cầu.'
            ]);
        }

        $request->status = LeaseOwnershipRequest::STATUS_APPROVED;
        $request->approved_by = $actor->id;
        $request->approved_at = Carbon::now();
        $request->approval_reason = $reason;
        $request->save();

        LeaseOwnershipEvent::create([
            'ownership_request_id' => $request->id,
            'from_status' => LeaseOwnershipRequest::STATUS_SUBMITTED,
            'to_status' => LeaseOwnershipRequest::STATUS_APPROVED,
            'actor_user_id' => $actor->id,
            'notes' => 'Phê duyệt chuyển quyền: ' . $reason,
        ]);

        AuditService::log('lease.ownership.approve', $request, null, $request->toArray(), 'Phê duyệt chuyển quyền sở hữu', $request->store_id);

        return $request;
    }

    /**
     * Reject ownership transfer request.
     */
    public function reject(int $requestId, User $actor, string $reason): LeaseOwnershipRequest
    {
        $request = LeaseOwnershipRequest::findOrFail($requestId);
        PermissionAccess::can($actor, 'lease.ownership_approve', $request->store_id);

        if (!in_array($request->status, [LeaseOwnershipRequest::STATUS_DRAFT, LeaseOwnershipRequest::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Không thể từ chối hồ sơ ở trạng thái hiện tại.'
            ]);
        }

        $fromStatus = $request->status;
        $request->status = LeaseOwnershipRequest::STATUS_REJECTED;
        $request->rejected_by = $actor->id;
        $request->rejected_at = Carbon::now();
        $request->rejection_reason = $reason;
        $request->save();

        LeaseOwnershipEvent::create([
            'ownership_request_id' => $request->id,
            'from_status' => $fromStatus,
            'to_status' => LeaseOwnershipRequest::STATUS_REJECTED,
            'actor_user_id' => $actor->id,
            'notes' => 'Từ chối chuyển quyền: ' . $reason,
        ]);

        AuditService::log('lease.ownership.reject', $request, null, $request->toArray(), 'Từ chối chuyển quyền sở hữu', $request->store_id);

        return $request;
    }

    /**
     * Atomically execute ownership transfer in strict lock order.
     */
    public function executeTransfer(int $requestId, User $actor, ?string $notes = null, ?string $idempotencyKey = null): LeaseOwnershipRequest
    {
        $existingReq = LeaseOwnershipRequest::findOrFail($requestId);
        PermissionAccess::can($actor, 'lease.ownership_execute', $existingReq->store_id);

        $enabled = filter_var(env('HIMOTO_ENABLE_OWNERSHIP_EXECUTE', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            throw ValidationException::withMessages([
                'feature_flag' => 'Chức năng thực thi chuyển quyền sở hữu đang tạm khóa chờ Ban Giám Đốc và Cố vấn Pháp lý phê duyệt quy chế chính thức (HIMOTO_ENABLE_OWNERSHIP_EXECUTE=false).'
            ]);
        }

        if ($existingReq->status === LeaseOwnershipRequest::STATUS_EXECUTED) {
            return $existingReq;
        }

        return DB::transaction(function () use ($requestId, $actor, $idempotencyKey, $notes) {
            // Lock in strict canonical order: LeaseContract -> Vehicle -> LeaseOwnershipRequest
            $reqTemp = LeaseOwnershipRequest::findOrFail($requestId);
            
            $contract = LeaseContract::where('id', $reqTemp->lease_contract_id)->lockForUpdate()->firstOrFail();
            $vehicle = Vehicle::where('id', $reqTemp->vehicle_id)->lockForUpdate()->firstOrFail();
            $request = LeaseOwnershipRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== LeaseOwnershipRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ hồ sơ đã được phê duyệt (approved) mới có thể thực thi chuyển quyền.'
                ]);
            }

            // Re-verify invariant: debt must be 0
            $activePaid = (float) $contract->allocations()->effectivePayments()->sum('amount');
            $discount = (float) ($contract->discount_amount ?? 0);
            $remainingDebt = max(0, (float) $contract->total_amount - $activePaid - $discount);

            if ($remainingDebt > 0.01) {
                throw ValidationException::withMessages([
                    'debt' => 'Dư nợ thực tế lớn hơn 0 (' . number_format($remainingDebt, 0, ',', '.') . ' đ). Không được phép thực thi chuyển quyền.'
                ]);
            }

            $now = Carbon::now();
            $certNum = 'GCN-' . $now->format('Ymd') . '-' . Str::upper(Str::random(5));

            // 1. Update Request
            $request->status = LeaseOwnershipRequest::STATUS_EXECUTED;
            $request->executed_by = $actor->id;
            $request->executed_at = $now;
            $request->execution_notes = $notes;
            if ($idempotencyKey) {
                $request->idempotency_key = $idempotencyKey;
            }
            $request->save();

            // 2. Create Vehicle Ownership Record
            VehicleOwnership::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $contract->customer_id,
                'lease_contract_id' => $contract->id,
                'ownership_request_id' => $request->id,
                'transferred_at' => $now,
                'certificate_number' => $certNum,
                'notes' => 'Bàn giao chuyển quyền sở hữu thành công theo quyết định duyệt ngày ' . ($request->approved_at ? $request->approved_at->format('d/m/Y') : $now->format('d/m/Y')),
            ]);

            // 3. Update Vehicle status
            $vehicle->status = 'transferred';
            $vehicle->save();

            // 4. Update Contract notes
            $contract->status = LeaseContract::STATUS_COMPLETED;
            $contract->notes = trim(($contract->notes ? $contract->notes . "\n" : "") . "[ĐÃ CHUYỂN QUYỀN SỞ HỮU XE] Mã chứng nhận: {$certNum}, thực thi bởi {$actor->name} ngày {$now->format('d/m/Y H:i')}.");
            $contract->save();

            // 5. Create immutable event
            LeaseOwnershipEvent::create([
                'ownership_request_id' => $request->id,
                'from_status' => LeaseOwnershipRequest::STATUS_APPROVED,
                'to_status' => LeaseOwnershipRequest::STATUS_EXECUTED,
                'actor_user_id' => $actor->id,
                'notes' => "Thực thi chuyển quyền sở hữu xe thành công. Mã GCN: {$certNum}. " . $notes,
            ]);

            AuditService::log('lease.ownership.execute', $request, null, [
                'certificate_number' => $certNum,
                'vehicle_id' => $vehicle->id,
                'customer_id' => $contract->customer_id,
                'executed_at' => $now->toDateTimeString(),
            ], 'Thực thi chuyển quyền sở hữu xe', $request->store_id);

            return $request;
        });
    }
}
