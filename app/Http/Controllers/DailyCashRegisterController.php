<?php

namespace App\Http\Controllers;

use App\Http\Services\CashRegisterService;
use App\Models\DailyCashRegister;
use App\Support\PilotAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyCashRegisterController extends Controller
{
    protected $cashRegisterService;

    public function __construct(CashRegisterService $cashRegisterService)
    {
        $this->cashRegisterService = $cashRegisterService;
    }

    /**
     * Lấy tóm tắt sổ két theo ngày và cơ sở.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $date = $request->input('date', date('Y-m-d'));
        $requestedStoreId = $request->input('store_id');

        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($requestedStoreId !== null && (int)$requestedStoreId !== (int)$user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem sổ két của cơ sở khác.', 403);
            }
            $storeId = (int)$user->store_id;
        } else {
            $storeId = $requestedStoreId ? (int)$requestedStoreId : null;
        }

        $summary = $this->cashRegisterService->getDailySummary($storeId, $date, $user->id);

        return $this->successResponse($summary);
    }

    /**
     * Chốt két ngày (Admin/Kế toán).
     */
    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'actual_cash_counted' => 'required|numeric|min:0',
            'difference_reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        if (!$user || !PilotAccess::isAdmin($user)) {
            return $this->errorResponse('Chỉ Admin hoặc Kế toán mới có quyền chốt két ngày.', 403);
        }

        try {
            $register = $this->cashRegisterService->closeDailyRegister(
                (int) $request->input('store_id'),
                $request->input('date'),
                (float) $request->input('actual_cash_counted'),
                $request->input('difference_reason'),
                $user->id,
                $request->input('notes')
            );

            return $this->successResponse($register, 'Chốt két ngày thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Mở lại két ngày (Admin only).
     */
    public function reopen(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $user = Auth::user();
        if (!$user || !PilotAccess::isAdmin($user)) {
            return $this->errorResponse('Chỉ Admin mới có quyền mở lại sổ két.', 403);
        }

        try {
            $register = $this->cashRegisterService->reopenDailyRegister(
                (int) $request->input('store_id'),
                $request->input('date'),
                $user->id
            );

            return $this->successResponse($register, 'Đã mở lại sổ két thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Lịch sử các ngày chốt két.
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $query = DailyCashRegister::with(['store', 'closedByUser'])
            ->orderBy('register_date', 'desc');

        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($request->filled('store_id') && (int)$request->input('store_id') !== (int)$user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem lịch sử két của cơ sở khác.', 403);
            }
            $query->where('store_id', (int)$user->store_id);
        } else {
            if ($request->filled('store_id')) {
                $query->where('store_id', (int)$request->input('store_id'));
            }
        }

        if ($request->filled('from_date')) {
            $query->where('register_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('register_date', '<=', $request->input('to_date'));
        }

        $history = $query->paginate($request->input('per_page', 15));

        return $this->successResponse($history);
    }
}
