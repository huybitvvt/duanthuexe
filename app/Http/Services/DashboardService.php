<?php

namespace App\Http\Services;

use App\Helpers\DateTimeHelper;
use App\Models\Vehicle;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return everything the dashboard needs in one HTTP request.
     */
    public function overview($requestedStoreId = null): array
    {
        $now = DateTimeHelper::now();

        return [
            'report' => $this->report($requestedStoreId),
            'chart' => $this->reportChart(
                $now->copy()->startOfMonth(),
                $now,
                $requestedStoreId
            ),
        ];
    }

    public function reportChart(Carbon $startDate, Carbon $endDate, $requestedStoreId = null): array
    {
        $storeId = $this->resolveStoreId($requestedStoreId);
        $key = 'dashboard:v3:chart:' . ($storeId ?: 'all') . ':' . $startDate->toDateString() . ':' . $endDate->toDateString();

        return Cache::remember($key, $this->cacheUntil(), function () use ($startDate, $endDate, $storeId) {
            $rangeStart = $startDate->copy()->startOfDay();
            $rangeEnd = $endDate->copy()->endOfDay();

            $orders = DB::table('orders')
                ->selectRaw("DATE(created_at) as day, COALESCE(SUM(CAST(NULLIF(total, '') AS DECIMAL)), 0) as total")
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->when($storeId, function (Builder $query) use ($storeId) {
                    $query->where('store_id', $storeId);
                })
                ->groupBy(DB::raw('DATE(created_at)'));

            $transactions = DB::table('transactions')
                ->selectRaw('DATE(created_at) as day, COALESCE(SUM(value), 0) as total')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->when($storeId, function (Builder $query) use ($storeId) {
                    $query->where('store_id', $storeId);
                })
                ->groupBy(DB::raw('DATE(created_at)'));

            $dailyTotals = DB::query()
                ->fromSub($orders->unionAll($transactions), 'daily_totals')
                ->select('day')
                ->selectRaw('SUM(total) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $labels = [];
            $values = [];
            for ($day = $startDate->copy()->startOfDay(); $day->lte($endDate); $day->addDay()) {
                $date = $day->toDateString();
                $labels[] = $day->format('d-m-Y');
                $values[] = (float) ($dailyTotals[$date] ?? 0);
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    public function report($requestedStoreId = null): array
    {
        $storeId = $this->resolveStoreId($requestedStoreId);
        $key = 'dashboard:v3:report:' . ($storeId ?: 'all');

        return Cache::remember($key, $this->cacheUntil(), function () use ($storeId) {
            return $this->buildReport($storeId);
        });
    }

    /**
     * Build all KPI values in a single database round-trip. The previous
     * implementation issued 35 sequential queries and loaded whole monthly
     * collections into PHP before summing them.
     */
    private function buildReport(?int $storeId = null): array
    {
        $now = DateTimeHelper::now();
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $now->copy()->endOfDay();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $vehicleMetrics = DB::table('vehicles')
            ->selectRaw(
                'COUNT(*) as total_vehicle,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_using,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_ready,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_repairing,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_broken',
                [
                    Vehicle::STATUS_USING,
                    Vehicle::STATUS_READY,
                    Vehicle::STATUS_REPAIRING,
                    Vehicle::STATUS_BROKEN,
                ]
            )
            ->when($storeId, function (Builder $query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        $orderMetrics = DB::table('orders')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND (order_status IS NULL OR order_status != ?) THEN 1 ELSE 0 END), 0) as total_order_in_day,
                 COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND (order_status IS NULL OR order_status != ?) THEN 1 ELSE 0 END), 0) as total_order_in_month,
                 COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND out_dated_at > 0 AND order_status = ? THEN 1 ELSE 0 END), 0) as total_order_out_date_in_month,
                 COALESCE(SUM(CASE WHEN completed_at BETWEEN ? AND ? AND order_status = ? THEN COALESCE(first_deposit_amount, 0) + COALESCE(additional_deposit_amount, 0) ELSE 0 END), 0) as total_origin_refund_in_day_new,
                 COALESCE(SUM(CASE WHEN completed_at BETWEEN ? AND ? AND order_status = ? THEN COALESCE(first_deposit_amount, 0) + COALESCE(additional_deposit_amount, 0) ELSE 0 END), 0) as total_origin_refund_in_month_new',
                [
                    $startOfDay, $endOfDay, OrderValidator::ORDER_DRAFT,
                    $startOfMonth, $endOfMonth, OrderValidator::ORDER_DRAFT,
                    $startOfMonth, $endOfMonth, OrderValidator::ORDER_RENTING,
                    $startOfDay, $endOfDay, OrderValidator::ORDER_COMPLETED,
                    $startOfMonth, $endOfMonth, OrderValidator::ORDER_COMPLETED,
                ]
            )
            ->whereNull('deleted_at')
            ->when($storeId, function (Builder $query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        $transactionMetrics = DB::table('transactions as dashboard_transactions')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND (dashboard_transactions.name LIKE ? OR dashboard_transactions.name = ?) THEN dashboard_transactions.value ELSE 0 END), 0) as total_deposit_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.name = ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_renew_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND dashboard_transactions.name = ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_rental_fees_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND dashboard_transactions.name LIKE ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_refund_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND (dashboard_transactions.name LIKE ? OR dashboard_transactions.name = ?) THEN dashboard_transactions.value ELSE 0 END), 0) as total_deposit_in_month_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.name = ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_renew_in_month_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND dashboard_transactions.name = ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_rental_fees_in_month_new,
                 COALESCE(SUM(CASE WHEN dashboard_transactions.created_at BETWEEN ? AND ? AND dashboard_transactions.type = ? AND dashboard_transactions.name LIKE ? THEN dashboard_transactions.value ELSE 0 END), 0) as total_refund_in_month_new',
                [
                    $startOfDay, $endOfDay, 'in', 'order:deposit%', 'order:additional_deposit',
                    $startOfDay, $endOfDay, 'addon',
                    $startOfDay, $endOfDay, 'in', 'order:rental_fees',
                    $startOfDay, $endOfDay, 'out', 'order:complete%',
                    $startOfMonth, $endOfMonth, 'in', 'order:deposit%', 'order:additional_deposit',
                    $startOfMonth, $endOfMonth, 'addon',
                    $startOfMonth, $endOfMonth, 'in', 'order:rental_fees',
                    $startOfMonth, $endOfMonth, 'out', 'order:complete%',
                ]
            );

        if ($storeId) {
            $transactionMetrics
                ->join('orders as transaction_orders', 'transaction_orders.id', '=', 'dashboard_transactions.order_id')
                ->where('transaction_orders.store_id', $storeId);
        }

        $orderItemMetrics = DB::table('order_vehicle_details as dashboard_order_items')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN dashboard_order_items.completed_at BETWEEN ? AND ? AND dashboard_order_items.handler_price = 0 AND dashboard_order_items.money_out_date < 0 THEN dashboard_order_items.money_out_date ELSE 0 END), 0) as total_money_early_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_order_items.completed_at BETWEEN ? AND ? AND dashboard_order_items.handler_price = 0 AND dashboard_order_items.money_out_date > 0 THEN dashboard_order_items.money_out_date ELSE 0 END), 0) as total_money_out_date_in_day_new,
                 COALESCE(SUM(CASE WHEN dashboard_order_items.completed_at BETWEEN ? AND ? AND dashboard_order_items.handler_price = 0 AND dashboard_order_items.money_out_date < 0 THEN dashboard_order_items.money_out_date ELSE 0 END), 0) as total_money_early_in_month_new,
                 COALESCE(SUM(CASE WHEN dashboard_order_items.completed_at BETWEEN ? AND ? AND dashboard_order_items.handler_price = 0 AND dashboard_order_items.money_out_date > 0 THEN dashboard_order_items.money_out_date ELSE 0 END), 0) as total_money_out_date_in_month_new',
                [
                    $startOfDay, $endOfDay,
                    $startOfDay, $endOfDay,
                    $startOfMonth, $endOfMonth,
                    $startOfMonth, $endOfMonth,
                ]
            )
            ->whereNull('dashboard_order_items.deleted_at');

        if ($storeId) {
            $orderItemMetrics
                ->join('orders as item_orders', 'item_orders.id', '=', 'dashboard_order_items.order_id')
                ->where('item_orders.store_id', $storeId);
        }

        $customerMetrics = DB::table('customers')
            ->selectRaw('COUNT(*) as total_customer');
        if ($storeId) {
            $customerMetrics->whereExists(function (Builder $query) use ($storeId) {
                $query->select(DB::raw(1))
                    ->from('orders as customer_orders')
                    ->whereColumn('customer_orders.customer_id', 'customers.id')
                    ->where('customer_orders.store_id', $storeId)
                    ->whereNull('customer_orders.deleted_at');
            });
        }

        $staffMetrics = DB::table('users')
            ->selectRaw('COUNT(*) as total_staff')
            ->where('role_id', '!=', 1)
            ->whereNull('deleted_at')
            ->when($storeId, function (Builder $query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        $metrics = DB::query()
            ->fromSub($vehicleMetrics, 'vehicle_metrics')
            ->joinSub($orderMetrics, 'order_metrics', function () {
            }, null, null, 'cross')
            ->joinSub($transactionMetrics, 'transaction_metrics', function () {
            }, null, null, 'cross')
            ->joinSub($orderItemMetrics, 'order_item_metrics', function () {
            }, null, null, 'cross')
            ->joinSub($customerMetrics, 'customer_metrics', function () {
            }, null, null, 'cross')
            ->joinSub($staffMetrics, 'staff_metrics', function () {
            }, null, null, 'cross')
            ->select([
                'vehicle_metrics.*',
                'order_metrics.*',
                'transaction_metrics.*',
                'order_item_metrics.*',
                'customer_metrics.*',
                'staff_metrics.*',
            ])
            ->first();

        $result = (array) $metrics;
        foreach ([
            'total_vehicle',
            'total_vehicle_using',
            'total_vehicle_ready',
            'total_vehicle_repairing',
            'total_vehicle_broken',
            'total_customer',
            'total_staff',
            'total_order_in_month',
            'total_order_in_day',
            'total_order_out_date_in_month',
        ] as $field) {
            $result[$field] = (int) ($result[$field] ?? 0);
        }

        foreach ([
            'total_deposit_in_day_new',
            'total_renew_in_day_new',
            'total_rental_fees_in_day_new',
            'total_origin_refund_in_day_new',
            'total_refund_in_day_new',
            'total_money_early_in_day_new',
            'total_money_out_date_in_day_new',
            'total_deposit_in_month_new',
            'total_renew_in_month_new',
            'total_rental_fees_in_month_new',
            'total_origin_refund_in_month_new',
            'total_refund_in_month_new',
            'total_money_early_in_month_new',
            'total_money_out_date_in_month_new',
        ] as $field) {
            $result[$field] = (float) ($result[$field] ?? 0);
        }

        return $result;
    }

    private function resolveStoreId($requestedStoreId): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ((int) $user->role_id !== 1) {
            return $user->store_id ? (int) $user->store_id : null;
        }

        if (!$requestedStoreId || $requestedStoreId === 'all') {
            return null;
        }

        return (int) $requestedStoreId;
    }

    private function cacheUntil(): Carbon
    {
        return Carbon::now()->addSeconds(max(5, (int) config('performance.dashboard_cache_seconds', 30)));
    }
}
