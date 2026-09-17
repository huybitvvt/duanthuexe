<?php

namespace App\Http\Controllers;

use App\Http\Services\AuditService;
use App\Http\Services\CustomerReminderService;
use App\Http\Services\Gps\GpsService;
use App\Support\PermissionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     */
    public function actionList(Request $request): JsonResponse
    {
        $user = Auth::user();
        PermissionAccess::can($user, 'reminder.view');

        $data = $this->reminderService->getStaffActionList($request->all());
        return $this->successResponse($data);
    }

    /**
     * Scan due and overdue contracts.
     */
    public function scan(): JsonResponse
    {
        $user = Auth::user();
        PermissionAccess::can($user, 'reminder.manage');

        $res = $this->reminderService->scanDueAndOverdueItems();

        AuditService::log('reminder.scan', 'customer_reminder_outbox', null, $res, 'Quét nhắc nợ đến hạn/quá hạn');

        return $this->successResponse($res, 'Quét hợp đồng đến hạn & quá hạn hoàn tất.');
    }

    /**
     * Process pending outbox (dry-run or live).
     */
    public function dispatchOutbox(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'dry_run' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $dryRun = filter_var($request->get('dry_run', true), FILTER_VALIDATE_BOOLEAN);
        $limit = (int) $request->get('limit', 50);

        if ($dryRun) {
            PermissionAccess::can($user, 'reminder.view');
        } else {
            // Live dispatch strictly requires reminder.dispatch_live permission
            PermissionAccess::can($user, 'reminder.dispatch_live');

            AuditService::log('reminder.dispatch_live', 'customer_reminder_outbox', null, [
                'limit' => $limit,
                'actor_id' => $user->id,
            ], 'Kích hoạt gửi nhắc nợ thực tế');
        }

        $res = $this->reminderService->processOutbox($limit, (bool) $dryRun);
        return $this->successResponse($res, $dryRun ? 'Xử lý mô phỏng (dry-run) hoàn tất.' : 'Xử lý gửi thật hoàn tất.');
    }

    /**
     * External webhook receiver for delivery status reports.
     */
    public function webhook(string $provider, Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        // Normalize header array format
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower($k)] = is_array($v) ? ($v[0] ?? '') : $v;
        }

        $result = $this->reminderService->handleWebhook($provider, $payload, $normalizedHeaders);
        return response()->json($result);
    }

    /**
     * Get GPS fleet overview.
     */
    public function gpsOverview(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
        ]);
        $storeId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;
        PermissionAccess::can($user, 'gps.view', $storeId);
        $res = $this->gpsService->getFleetOverview($user, $storeId);
        return $this->successResponse($res);
    }
}
