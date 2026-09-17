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

    public function transactions(Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date_format:Y-m-d', 'store_id' => 'required|integer', 'limit' => 'nullable|integer|min:1|max:500']);
        try {
            $storeId = $this->resolveStoreId($request);
            return $this->successResponse($this->cashRegisterService->getDailyTransactions($storeId, $request->input('date'), (int)$request->input('limit', 100)));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function sources(Request $request): JsonResponse
    {
        $request->validate(['store_id' => 'required|integer']);
        try {
            return $this->successResponse($this->cashRegisterService->getPaymentSources($this->resolveStoreId($request)));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d',
            'type' => 'required|in:in,out', 'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:1000', 'channel' => 'required|in:cash,bank',
            'cash_id' => 'required_if:channel,cash|nullable|integer', 'bank_id' => 'required_if:channel,bank|nullable|integer',
        ]);
        try {
            $data = $request->all();
            $data['store_id'] = $this->resolveStoreId($request);
            return $this->successResponse($this->cashRegisterService->createManualEntry($data, Auth::id()), 'Đã ghi giao dịch thu/chi.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function storeExchange(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d',
            'direction' => 'required|in:cash_to_bank,bank_to_cash', 'amount' => 'required|numeric|min:1',
            'cash_id' => 'required|integer', 'bank_id' => 'required|integer', 'description' => 'nullable|string|max:1000',
        ]);
        try {
            $data = $request->all();
            $data['store_id'] = $this->resolveStoreId($request);
            return $this->successResponse($this->cashRegisterService->createCashBankExchange($data, Auth::id()), 'Đã tạo cặp bút toán đổi tiền.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    private function resolveStoreId(Request $request): int
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Chưa đăng nhập.');
        }
        $storeId = (int)$request->input('store_id');
        if (!PilotAccess::isAdmin($user) && (int)$user->store_id !== $storeId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Bạn không có quyền thao tác sổ két của cơ sở khác.');
        }
        return $storeId;
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
