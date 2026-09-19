<?php

namespace App\Http\Controllers;

use App\Helpers\DateTimeHelper;
use App\Models\MaintenanceSchedule;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get system notification summary for the header notification bell.
     * Aggregates:
     *  1. Vehicles with upcoming or overdue maintenance
     *  2. Overdue rental contracts
     */
    public function summary(Request $request): JsonResponse
    {
        $now = DateTimeHelper::now();
        $storeId = $request->input('store_id');
        $limitDate = $now->copy()->addDays(7)->endOfDay();

        // 1. Maintenance alerts
        $maintenanceQuery = MaintenanceSchedule::query()
            ->with(['maintenanceType:id,name', 'vehicle:id,name,license,store_id'])
            ->where(function ($q) use ($limitDate) {
                $q->whereNotNull('next_time_manual')
                  ->where('next_time_manual', '<=', $limitDate);
            })
            ->orWhere(function ($q) use ($limitDate) {
                $q->whereNotNull('next_time_auto')
                  ->where('next_time_auto', '<=', $limitDate);
            });

        if ($storeId && $storeId !== 'all') {
            $maintenanceQuery->whereHas('vehicle', function ($vq) use ($storeId) {
                $vq->where('store_id', (int) $storeId);
            });
        }

        $allMaintenance = $maintenanceQuery->orderByRaw("COALESCE(next_time_manual, next_time_auto) ASC")
            ->take(30)
            ->get();

        $maintenanceItems = $allMaintenance->map(function ($item) use ($now) {
            $dueDate = $item->next_time_manual ?: $item->next_time_auto;
            $carbonDue = Carbon::parse($dueDate)->timezone('Asia/Bangkok');

            if ($carbonDue->lt($now->copy()->startOfDay())) {
                $daysOver = (int) $now->diffInDays($carbonDue);
                $status = 'overdue';
                $statusText = $daysOver <= 1 ? 'Quá hạn 1 ngày' : "Quá hạn {$daysOver} ngày";
                $severity = 'danger';
            } elseif ($carbonDue->isToday()) {
                $status = 'today';
                $statusText = 'Đến hạn hôm nay';
                $severity = 'warning';
            } else {
                $daysLeft = (int) ceil($now->diffInHours($carbonDue) / 24);
                $status = 'upcoming';
                $statusText = "Còn {$daysLeft} ngày";
                $severity = 'info';
            }

            return [
                'id' => $item->id,
                'vehicle_id' => $item->vehicle_id,
                'vehicle_name' => $item->vehicle ? $item->vehicle->name : 'Xe #' . $item->vehicle_id,
                'vehicle_license' => $item->vehicle ? $item->vehicle->license : '',
                'maintenance_type_name' => $item->maintenanceType ? $item->maintenanceType->name : 'Bảo dưỡng',
                'due_date' => $carbonDue->format('d/m/Y H:i'),
                'status' => $status,
                'status_text' => $statusText,
                'severity' => $severity,
            ];
        });

        // 2. Overdue rental contracts (orders)
        $orderQuery = Order::query()
            ->with(['customer:id,name,phone', 'vehicle:id,name,license'])
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('order_status', 'renting')
                  ->orWhere('order_status', 2);
            })
            ->where(function ($q) use ($now) {
                $q->where('is_out_of_date', 1)
                  ->orWhere('is_out_of_date', true)
                  ->orWhere('return_at', '<', $now);
            });

        if ($storeId && $storeId !== 'all') {
            $orderQuery->where('store_id', (int) $storeId);
        }

        $allOrders = $orderQuery->orderBy('return_at', 'ASC')->take(20)->get();

        $orderItems = $allOrders->map(function ($order) use ($now) {
            $returnAt = $order->return_at ? Carbon::parse($order->return_at)->timezone('Asia/Bangkok') : null;
            $daysOver = $returnAt ? (int) $now->diffInDays($returnAt) : 0;

            return [
                'id' => $order->id,
                'contract_number' => $order->contract_number ?: ('HĐ #' . $order->id),
                'customer_name' => $order->customer ? $order->customer->name : 'Khách #' . $order->customer_id,
                'customer_phone' => $order->customer ? $order->customer->phone : '',
                'vehicle_license' => $order->vehicle ? $order->vehicle->license : '',
                'return_at' => $returnAt ? $returnAt->format('d/m/Y H:i') : '',
                'days_overdue' => $daysOver,
                'status_text' => $daysOver <= 1 ? 'Quá hạn 1 ngày' : "Quá hạn {$daysOver} ngày",
                'severity' => 'danger',
            ];
        });

        $totalBadge = $maintenanceItems->count() + $orderItems->count();

        return $this->successResponse([
            'total_badge' => $totalBadge,
            'maintenance' => [
                'count' => $maintenanceItems->count(),
                'items' => $maintenanceItems,
            ],
            'orders' => [
                'count' => $orderItems->count(),
                'items' => $orderItems,
            ],
        ], 'Lấy thông tin thông báo thành công.');
    }
}
