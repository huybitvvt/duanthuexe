<?php

namespace App\Http\Controllers;

use App\Http\Services\LeaseOwnershipService;
use App\Models\LeaseOwnershipRequest;
use App\Support\PermissionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaseOwnershipController extends Controller
{
    protected $ownershipService;

    public function __construct(LeaseOwnershipService $ownershipService)
    {
        $this->ownershipService = $ownershipService;
    }

    /**
     * Get list of lease ownership transfer requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        PermissionAccess::can($user, 'lease.view');

        $query = LeaseOwnershipRequest::with([
            'contract',
            'customer',
            'vehicle',
            'store',
            'requester',
            'approver',
            'executor',
            'events.actor',
        ]);

        if (!PermissionAccess::isSuperAdmin($user) && !PermissionAccess::allows($user, 'kpi.view_company')) {
            // User::stores() relies on an optional pivot in older deployments;
            // use the mandatory account store scope for this sensitive list.
            if ($user->store_id) {
                $query->where('store_id', (int) $user->store_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('contract_id')) {
            $query->where('lease_contract_id', $request->contract_id);
        }

        $perPage = (int) $request->get('per_page', 15);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($paginated);
    }

    /**
     * View single request detail.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $req = LeaseOwnershipRequest::with([
            'contract.installments',
            'contract.allocations',
            'customer',
            'vehicle',
            'store',
            'requester',
            'approver',
            'executor',
            'events.actor',
        ])->findOrFail($id);

        PermissionAccess::can($user, 'lease.view', $req->store_id);

        return $this->successResponse($req);
    }

    /**
     * Create draft ownership request.
     */
    public function createDraft(int $contractId, Request $request): JsonResponse
    {
        $user = Auth::user();
        $checklist = $request->input('checklist', []);
        $req = $this->ownershipService->createDraft($contractId, $user, $checklist);

        return $this->successResponse($req, 'Đã tạo hồ sơ chuyển quyền sở hữu nháp.');
    }

    /**
     * Submit request for approval.
     */
    public function submit(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        $notes = $request->input('notes', '');
        $req = $this->ownershipService->submitForApproval($id, $user, $notes);

        return $this->successResponse($req, 'Đã nộp hồ sơ chuyển quyền sở hữu chờ phê duyệt.');
    }

    /**
     * Approve request (Maker-checker enforced).
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        $reason = $request->input('reason', '');
        $req = $this->ownershipService->approve($id, $user, $reason);

        return $this->successResponse($req, 'Đã phê duyệt chuyển quyền sở hữu.');
    }

    /**
     * Reject request.
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        $reason = $request->input('reason', '');
        $req = $this->ownershipService->reject($id, $user, $reason);

        return $this->successResponse($req, 'Đã từ chối hồ sơ chuyển quyền sở hữu.');
    }

    /**
     * Execute ownership transfer atomically.
     */
    public function executeTransfer(int $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        $notes = $request->input('notes', '');
        $idempotencyKey = $request->header('X-Idempotency-Key') ?: $request->input('idempotency_key');

        $req = $this->ownershipService->executeTransfer($id, $user, $notes, $idempotencyKey);

        return $this->successResponse($req, 'Thực thi chuyển quyền sở hữu thành công.');
    }
}
