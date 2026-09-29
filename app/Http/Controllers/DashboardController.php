<?php

namespace App\Http\Controllers;

use App\Http\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;
use App\Support\PermissionAccess;

class DashboardController extends Controller
{
    private $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function report(Request $request)
    {
        return $this->successResponse($this->dashboardService->report($this->authorizedStoreId($request)));
    }

    /**
     * Load KPI cards and chart data with one authenticated HTTP request.
     */
    public function overview(Request $request)
    {
        return $this->successResponse($this->dashboardService->overview($this->authorizedStoreId($request)));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function reportChart(Request $request)
    {
        $now = DateTimeHelper::now();
        $startDate = $now->copy()->startOfMonth();
        $endDate = $now;
        return $this->successResponse($this->dashboardService->reportChart($startDate, $endDate, $this->authorizedStoreId($request)));
    }

    private function authorizedStoreId(Request $request)
    {
        $user = $request->user();
        $requestedStoreId = $request->filled('store_id') ? (int) $request->input('store_id') : null;
        $storeId = $requestedStoreId ?: ($user->store_id ? (int) $user->store_id : null);
        PermissionAccess::can($user, 'dashboard.view_store', $storeId);
        if (!$storeId && !in_array(PermissionAccess::getRoleSlug($user), ['ban-giam-doc', 'van-hanh'], true)
            && !PermissionAccess::isAdmin($user)) {
            abort(403, 'Tài khoản chưa được gán cơ sở.');
        }
        return $storeId;
    }
}
