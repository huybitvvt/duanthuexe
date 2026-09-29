<?php

namespace App\Http\Controllers;

use App\Http\Services\BusinessApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessApprovalController extends Controller
{
    private $service;

    public function __construct(BusinessApprovalService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse($this->service->index($request->all(), Auth::user()));
    }

    public function submitLease(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:lease_schedule,lease_recovery,lease_liquidation',
            'reason' => 'required|string|max:2000',
            'plan' => 'nullable|string|max:5000',
            'installment_id' => 'nullable|integer',
            'period_number' => 'nullable|integer|min:1',
            'new_due_date' => 'nullable|date_format:Y-m-d',
        ]);
        return $this->successResponse(
            $this->service->submitLease($id, $request->input('action'), $request->all(), Auth::user()),
            'Đã gửi đề nghị chờ trưởng phòng duyệt.'
        );
    }

    public function submitOrder(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:order_cancel,order_discount',
            'reason' => 'required|string|max:2000',
            'amount' => 'required_if:action,order_discount|nullable|integer|min:1',
        ]);
        return $this->successResponse(
            $this->service->submitOrder($id, $request->input('action'), $request->all(), Auth::user()),
            'Đã gửi đề nghị chờ trưởng phòng duyệt.'
        );
    }

    public function settleCancellation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|in:cash,bank',
            'source_id' => 'nullable|integer|min:1',
            'note' => 'required|string|max:2000',
        ]);
        return $this->successResponse(
            $this->service->settleCancellation($id, $request->all(), Auth::user()),
            'Đã ghi nhận quyết toán hủy đơn.'
        );
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'decision' => 'required|in:approve,reject',
            'decision_note' => 'required|string|max:2000',
        ]);
        return $this->successResponse(
            $this->service->decide($id, $request->input('decision') === 'approve',
                $request->input('decision_note'), Auth::user()),
            'Đã xử lý đề nghị.'
        );
    }
}
