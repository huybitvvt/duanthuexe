<?php

namespace App\Http\Controllers;

use App\Http\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;

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
        return $this->successResponse($this->dashboardService->report($request->input('store_id')));
    }

    /**
     * Load KPI cards and chart data with one authenticated HTTP request.
     */
    public function overview(Request $request)
    {
        return $this->successResponse($this->dashboardService->overview($request->input('store_id')));
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
        return $this->successResponse($this->dashboardService->reportChart($startDate, $endDate, $request->input('store_id')));
    }
}
