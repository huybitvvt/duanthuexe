<?php

namespace App\Http\Controllers;

use App\Http\Services\CustomerReminderService;
use App\Http\Services\Gps\GpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReminderController extends Controller
{
    private $reminderService;
    private $gpsService;

    public function __construct(CustomerReminderService $reminderService, GpsService $gpsService)
    {
        $this->reminderService = $reminderService;
        $this->gpsService = $gpsService;
    }

    /**
     * Get staff action list for customer debt & return reminders.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actionList(Request $request): JsonResponse
    {
        $data = $this->reminderService->getStaffActionList($request->all());
        return $this->successResponse($data);
    }

    /**
     * Scan due and overdue contracts.
     *
     * @return JsonResponse
     */
    public function scan(): JsonResponse
    {
        $res = $this->reminderService->scanDueAndOverdueItems();
        return $this->successResponse($res, 'Quét hợp đồng đến hạn & quá hạn hoàn tất.');
    }

    /**
     * Process pending outbox.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dispatchOutbox(Request $request): JsonResponse
    {
        $dryRun = $request->get('dry_run', true);
        $res = $this->reminderService->processOutbox(50, (bool)$dryRun);
        return $this->successResponse($res, 'Xử lý hàng đợi nhắc khách thành công.');
    }

    /**
     * Get GPS fleet overview.
     *
     * @return JsonResponse
     */
    public function gpsOverview(): JsonResponse
    {
        $res = $this->gpsService->getFleetOverview();
        return $this->successResponse($res);
    }
}
