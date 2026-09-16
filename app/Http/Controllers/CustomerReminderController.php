<?php

namespace App\Http\Controllers;

use App\Http\Services\CustomerReminderService;
use App\Http\Services\Gps\GpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\PilotAccess;

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
        PilotAccess::admin(auth()->user());
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
        PilotAccess::admin(auth()->user());
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
        PilotAccess::admin(auth()->user());
        $request->validate(['dry_run' => 'nullable|boolean']);
        $dryRun = filter_var($request->get('dry_run', true), FILTER_VALIDATE_BOOLEAN);
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
        PilotAccess::admin(auth()->user());
        $res = $this->gpsService->getFleetOverview();
        return $this->successResponse($res);
    }
}
