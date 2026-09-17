<?php

namespace App\Http\Controllers;

use App\Http\Services\KpiReportService;
use App\Support\PilotAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KpiReportController extends Controller
{
    private $service;

    public function __construct(KpiReportService $service)
    {
        $this->service = $service;
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

        $storeId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;

        // Quyền xem toàn công ty (Admin, BGĐ)
        if (\App\Support\PermissionAccess::allows($user, 'kpi.view_company')) {
            // Cho phép xem mọi cơ sở hoặc lọc theo $storeId
        } elseif (\App\Support\PermissionAccess::allows($user, 'kpi.view_store', $user->store_id)) {
            // Chỉ xem được cơ sở của mình
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($storeId !== null && $storeId !== (int) $user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem KPI của cơ sở khác.', 403);
            }
            $storeId = (int) $user->store_id;
        } else {
            return $this->errorResponse('Bạn không có quyền truy cập báo cáo KPI.', 403);
        }

        $startDate = $validated['start_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        return $this->successResponse($this->service->build($startDate, $endDate, $storeId));
    }
}
