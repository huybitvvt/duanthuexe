<?php

namespace App\Http\Services;

use App\Interfaces\ICrud;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Repositories\VehicleRepository;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Services\ReportService;

class DashboardService
{
    private $vehicleRepository;
    private $customerRepository;
    private $userRepository;
    private $orderRepository;
    private $transactionRepository;
    private $reportService;

    public function __construct(
        VehicleRepository  $vehicleRepository,
        CustomerRepository $customerRepository,
        UserRepository     $userRepository,
        OrderRepository    $orderRepository, 
		TransactionRepository $transactionRepository,
		ReportService $reportService
    )
    {
        $this->vehicleRepository = $vehicleRepository;
        $this->customerRepository = $customerRepository;
        $this->userRepository = $userRepository;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->reportService = $reportService;
    }

    public function reportChart(Carbon $startDate, Carbon $endDate, $requestedStoreId = null)
    {
        $user = auth()->user();
        $isStoreIdRequested = ($requestedStoreId && $requestedStoreId !== 'all');
        $storeId = $user->role_rel->slug !== 'quan-tri-vien' ? $user->store_id : ($isStoreIdRequested ? $requestedStoreId : null);
        $key = 'dashboard:chart:' . ($storeId ?: 'all') . ':' . $startDate->toDateString() . ':' . $endDate->toDateString();
        return Cache::remember($key, 30, function () use ($startDate, $endDate, $storeId) {
            $orders = $this->orderRepository->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])->selectRaw('DATE(created_at) as day, COALESCE(SUM(total), 0) as total')->when($storeId, fn ($q) => $q->where('store_id', $storeId))->groupBy('day')->pluck('total', 'day');
            $transactions = Transaction::whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])->selectRaw('DATE(created_at) as day, COALESCE(SUM(value), 0) as total')->when($storeId, fn ($q) => $q->where('store_id', $storeId))->groupBy('day')->pluck('total', 'day');
            $labels = []; $values = [];
            for ($day = $startDate->copy()->startOfDay(); $day->lte($endDate); $day->addDay()) {
                $date = $day->toDateString(); $labels[] = $day->format('d-m-Y');
                $values[] = (float) ($orders[$date] ?? 0) + (float) ($transactions[$date] ?? 0);
            }
            return ['labels' => $labels, 'values' => $values];
        });
    }

    public function report($requestedStoreId = null)
    {
        $user = auth()->user();
        $isStoreIdRequested = ($requestedStoreId && $requestedStoreId !== 'all');
        $storeId = $user->role_rel->slug !== 'quan-tri-vien' ? $user->store_id : ($isStoreIdRequested ? $requestedStoreId : null);
        $key = 'dashboard:report:' . ($storeId ?: 'all');
        return Cache::remember($key, 30, fn () => $this->buildReport($storeId));
    }

    private function buildReport($storeId = null)
    {
        $now = DateTimeHelper::now();
        $start_day = $now->copy()->startOfDay();
        $end_day = $now->copy()->endOfDay();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $user = auth()->user();
        $effectiveStoreId = $user->role_rel->slug !== 'quan-tri-vien' ? $user->store_id : $storeId;

        $total_vehicle = $this->vehicleRepository->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_vehicle_using = $this->vehicleRepository->where('status', Vehicle::STATUS_USING)->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_vehicle_ready = $this->vehicleRepository->where('status', Vehicle::STATUS_READY)->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_vehicle_repairing = $this->vehicleRepository->where('status', Vehicle::STATUS_REPAIRING)->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_vehicle_broken = $this->vehicleRepository->where('status', Vehicle::STATUS_BROKEN)->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();

        $total_customer = $this->customerRepository->count();
        $total_staff = $this->userRepository->where('role_id', '!=', 1)->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_order_in_day = $this->orderRepository->findWhereBetween('created_at', [$start_day, $end_day])->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_order_in_month = $this->orderRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth])->when($effectiveStoreId, fn ($q) => $q->where('store_id', $effectiveStoreId))->count();
        $total_order_out_date_in_month_query = $this->orderRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth])->where('out_date_at', '>', 0)->where('order_status', OrderValidator::ORDER_RENTING);

		$total_profit_in_day_query = $this->transactionRepository->findWhereBetween('created_at', [$start_day, $end_day]);
		$total_profit_in_month_query = $this->transactionRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth]);

		$total_deposit_in_day_query = $this->orderRepository->findWhereBetween('created_at', [$start_day, $end_day]);
		$total_deposit_in_month_query = $this->orderRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth]);

		$total_refund_in_day_query = $this->transactionRepository->findWhereBetween('created_at', [$start_day, $end_day])->where("type", "out");
		$total_refund_in_month_query = $this->transactionRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth])->where("type", "out");

		$total_fee_in_day_query = $this->orderRepository->findWhereBetween('created_at', [$start_day, $end_day]);
		$total_fee_in_month_query = $this->orderRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth]);

		/** NEW QUERY */
		$total_deposit_new_query = $this->transactionRepository->where('type', 'in');
		$total_renew_new_query = $this->transactionRepository->where('name', 'addon');
		$total_rental_fees_new_query = $this->transactionRepository->where('name', 'rental_fees')->where('type', 'in');
		$total_refund_new_query = $this->transactionRepository->where("type", "out")->where('name', 'LIKE', "order:complete%");
		$total_money_early_new_query = DB::table('order_vehicle_details')->join('orders', 'orders.id', '=', 'order_vehicle_details.order_id')->where('order_vehicle_details.handler_price', '!=', 0)->where('order_vehicle_details.money_out_date', '<', 0);
		$total_money_out_date_new_query = DB::table('order_vehicle_details')->join('orders', 'orders.id', '=', 'order_vehicle_details.order_id')->where('order_vehicle_details.handler_price', '!=', 0)->where('order_vehicle_details.money_out_date', '>', 0);
		$total_origin_refund_new_query = DB::table('orders')->where('order_status', 'completed'); // Tổng số tiền cần refund mà chưa tính phí quá hạn hay trả sớm. VD hợp đồng A khách cọc 1tr thì khoản origin-refund phải là 1tr. Trên thực tế nếu phát sinh trả sớm hoặc trả muộn thì sẽ cộng trừ vào khoản cọc này.
		/** END NEW QUERY */
        
        if ($effectiveStoreId) {
            $total_order_out_date_in_month_query = $total_order_out_date_in_month_query->where('store_id', $effectiveStoreId);

            $total_profit_in_day_query = $total_profit_in_day_query->where('store_id', $effectiveStoreId);
            $total_profit_in_month_query = $total_profit_in_month_query->where('store_id', $effectiveStoreId);

            $total_deposit_in_day_query = $total_deposit_in_day_query->where('store_id', $effectiveStoreId);
            $total_deposit_in_month_query = $total_deposit_in_month_query->where('store_id', $effectiveStoreId);

            $total_refund_in_day_query = $total_refund_in_day_query->where('store_id', $effectiveStoreId);
            $total_refund_in_month_query = $total_refund_in_month_query->where('store_id', $effectiveStoreId);

            $total_fee_in_day_query = $total_fee_in_day_query->where('store_id', $effectiveStoreId);
            $total_fee_in_month_query = $total_fee_in_month_query->where('store_id', $effectiveStoreId);


			/** NEW QUERY */
			$total_deposit_new_query = $total_deposit_new_query->where('store_id', $effectiveStoreId);
			$total_renew_new_query = $total_renew_new_query->where('store_id', $effectiveStoreId);
			$total_rental_fees_new_query = $total_rental_fees_new_query->where('store_id', $effectiveStoreId);
			$total_refund_new_query = $total_refund_new_query->where('store_id', $effectiveStoreId);
			$total_money_early_new_query = $total_money_early_new_query->where('orders.store_id', $effectiveStoreId);
			$total_money_out_date_new_query = $total_money_out_date_new_query->where('orders.store_id', $effectiveStoreId);
			$total_origin_refund_new_query = $total_origin_refund_new_query->where('store_id', $effectiveStoreId);
			/** END NEW QUERY */
        }

		/** NEW QUERY */
		// Tổng thu cọc: name in( 'order:deposit:keep_vehicle', 'order:additional_deposit', 'order:deposit:11414' ) && type = in
		$total_deposit_new_query = $total_deposit_new_query->where(function ($query) {
			$query->where('name', 'LIKE', "order:deposit%")->orWhere('name', 'order:deposit:keep_vehicle')->orWhere('name', 'order:additional_deposit');
		});

		$total_deposit_in_day_new_query = $total_deposit_in_month_new_query = $total_deposit_new_query;
		$total_renew_in_day_new_query = $total_renew_in_month_new_query = $total_renew_new_query;
		$total_rental_fees_in_day_new_query = $total_rental_fees_in_month_new_query = $total_rental_fees_new_query;
		$total_refund_in_day_new_query = $total_refund_in_month_new_query = $total_refund_new_query;
		$total_money_early_in_day_new_query = $total_money_early_in_month_new_query = $total_money_early_new_query;
		$total_money_out_date_in_day_new_query = $total_money_out_date_in_month_new_query = $total_money_out_date_new_query;
		$total_origin_refund_in_day_new_query = $total_origin_refund_in_month_new_query = $total_origin_refund_new_query;

		/** END NEW QUERY */

		$total_order_out_date_in_month = $total_order_out_date_in_month_query->count();
        // $total_profit_in_day = $this->orderRepository->findWhereBetween('created_at', [$start_day, $end_day])->sum('total');
        // $total_profit_in_month = $this->orderRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('total');

		$total_profit_in_day = $total_profit_in_day_query->sum(function($transaction) {
			return in_array( $transaction->type, array( 'in', 'addon' ) ) ? $transaction->value : -$transaction->value;
		});
        $total_profit_in_month = $total_profit_in_month_query->sum(function($transaction) {
			return in_array( $transaction->type, array( 'in', 'addon' ) ) ? $transaction->value : -$transaction->value;
		});

		// Tổng thu cọc:
		$total_deposit_in_day = $total_deposit_in_day_query->sum('pid');
        $total_deposit_in_month = $total_deposit_in_month_query->sum('pid');

		// Tổng trả cọc: 
		// $total_refund_in_day = $this->transactionRepository->findWhereBetween('created_at', [$start_day, $end_day])->where("type", "=", "out")->where("name", "LIKE", "order:complete%")->sum('value');
		// $total_refund_in_month = $this->transactionRepository->findWhereBetween('created_at', [$startOfMonth, $endOfMonth])->where("type", "=", "out")->where("name", "LIKE", "order:complete%")->sum('value');

		$total_refund_in_day = $total_refund_in_day_query->sum(function($transaction) {
			return Str::contains($transaction->name, 'order:complete') ? $transaction->value : 0;
		});
		$total_refund_in_month = $total_refund_in_month_query->sum(function($transaction) {
			return Str::contains($transaction->name, 'order:complete') ? $transaction->value : 0;
		});

		// Tổng phí thuê + phạt quá hạn
		$total_fee_in_day = $total_fee_in_day_query->sum('total');
        $total_fee_in_month = $total_fee_in_month_query->sum('total');

		$result = [
            'total_vehicle' => $total_vehicle,
            'total_vehicle_ready' => $total_vehicle_ready,
            'total_vehicle_using' => $total_vehicle_using,
            'total_vehicle_repairing' => $total_vehicle_repairing,
            'total_vehicle_broken' => $total_vehicle_broken,
            //customer
            'total_customer' => $total_customer,
            'total_staff' => $total_staff,
            // Order
            'total_order_in_month' => $total_order_in_month,
            'total_order_in_day' => $total_order_in_day,
            'total_order_out_date_in_month' => $total_order_out_date_in_month,
            //Doanh thu
            // 'total_profit_in_day' => $total_profit_in_day,
            // 'total_profit_in_month' => $total_profit_in_month,

			// Tổng trả cọc
            // 'total_refund_in_day' => $total_refund_in_day,
            // 'total_refund_in_month' => $total_refund_in_month,

			// Tổng phí thuê + phạt quá hạn
			// 'total_fee_in_day' => $total_fee_in_day,
			// 'total_fee_in_month' => $total_fee_in_month,

			// Tổng thu cọc:
			// 'total_deposit_in_day' => $total_deposit_in_day,
			// 'total_deposit_in_month' => $total_deposit_in_month,

			/** NEW QUERY */
			// Tổng thu cọc: name in( 'order:deposit:keep_vehicle', 'order:additional_deposit', 'order:deposit:11414' ) && type = in
			'total_deposit_in_day_new' => 0,
			// Tổng thu gia hạn: name = addon && type = addon
			'total_renew_in_day_new' => 0,
			// Tổng thu phí thuê: name = order:rental_fees && type = in
			'total_rental_fees_in_day_new' => 0,

			// Tổng số tiền cọc đáng ra phải hoàn trả: chưa cộng trừ thêm khoản trả sớm, trả muộn.
			'total_origin_refund_in_day_new' => 0,
			// Tổng trả cọc: name like 'order:complete:11415' && type = out
			'total_refund_in_day_new' => 0,
				// Tổng trả sớm: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date < 0.
				'total_money_early_in_day_new' => 0,
				// Tổng phạt muộn: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date > 0.
				'total_money_out_date_in_day_new' => 0,

			// Tổng thu cọc: name in( 'order:deposit:keep_vehicle', 'order:additional_deposit', 'order:deposit:11414' ) && type = in
			'total_deposit_in_month_new' => 0,
			// Tổng thu gia hạn: name = addon && type = addon
			'total_renew_in_month_new' => 0,
			// Tổng thu phí thuê: name = order:rental_fees && type = in
			'total_rental_fees_in_month_new' => 0,
			// Tổng số tiền cọc đáng ra phải hoàn trả: chưa cộng trừ thêm khoản trả sớm, trả muộn.
			'total_origin_refund_in_month_new' => 0,
			// Tổng trả cọc: name like 'order:complete:11415' && type = out
			'total_refund_in_month_new' => 0,
				// Tổng trả sớm: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date < 0.
				'total_money_early_in_month_new' => 0,
				// Tổng phạt muộn: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date > 0.
				'total_money_out_date_in_month_new' => 0,

			/** END NEW QUERY */
			// => Tổng thu, Tổng chi.
        ];

		$report_by_day = $this->reportService->handleDetailReportNew( ['start_date' => $start_day, 'end_date' => $end_day] );
		$report_by_month = $this->reportService->handleDetailReportNew( ['start_date' => $startOfMonth, 'end_date' => $endOfMonth] );


		if ( is_array( $report_by_day ) && ! empty( $report_by_day ) ) {
			$result['total_deposit_in_day_new'] = ( isset( $report_by_day['total_deposit'] ) && ! empty( $report_by_day['total_deposit'] ) ) ? $report_by_day['total_deposit'] : 0;
			$result['total_renew_in_day_new'] = ( isset( $report_by_day['total_renew'] ) && ! empty( $report_by_day['total_renew'] ) ) ? $report_by_day['total_renew'] : 0;
			$result['total_rental_fees_in_day_new'] = ( isset( $report_by_day['total_rental_fees'] ) && ! empty( $report_by_day['total_rental_fees'] ) ) ? $report_by_day['total_rental_fees'] : 0;
			$result['total_origin_refund_in_day_new'] = ( isset( $report_by_day['total_origin_refund'] ) && ! empty( $report_by_day['total_origin_refund'] ) ) ? $report_by_day['total_origin_refund'] : 0;
			$result['total_refund_in_day_new'] = ( isset( $report_by_day['total_real_refund'] ) && ! empty( $report_by_day['total_real_refund'] ) ) ? $report_by_day['total_real_refund'] : 0;
			$result['total_money_early_in_day_new'] = ( isset( $report_by_day['total_money_early'] ) && ! empty( $report_by_day['total_money_early'] ) ) ? $report_by_day['total_money_early'] : 0;
			$result['total_money_out_date_in_day_new'] = ( isset( $report_by_day['total_money_out_date'] ) && ! empty( $report_by_day['total_money_out_date'] ) ) ? $report_by_day['total_money_out_date'] : 0;
		}

		if ( is_array( $report_by_month ) && ! empty( $report_by_month ) ) {
			$result['total_deposit_in_month_new'] = ( isset( $report_by_month['total_deposit'] ) && ! empty( $report_by_month['total_deposit'] ) ) ? $report_by_month['total_deposit'] : 0;
			$result['total_renew_in_month_new'] = ( isset( $report_by_month['total_renew'] ) && ! empty( $report_by_month['total_renew'] ) ) ? $report_by_month['total_renew'] : 0;
			$result['total_rental_fees_in_month_new'] = ( isset( $report_by_month['total_rental_fees'] ) && ! empty( $report_by_month['total_rental_fees'] ) ) ? $report_by_month['total_rental_fees'] : 0;
			$result['total_origin_refund_in_month_new'] = ( isset( $report_by_month['total_origin_refund'] ) && ! empty( $report_by_month['total_origin_refund'] ) ) ? $report_by_month['total_origin_refund'] : 0;
			$result['total_refund_in_month_new'] = ( isset( $report_by_month['total_real_refund'] ) && ! empty( $report_by_month['total_real_refund'] ) ) ? $report_by_month['total_real_refund'] : 0;
			$result['total_money_early_in_month_new'] = ( isset( $report_by_month['total_money_early'] ) && ! empty( $report_by_month['total_money_early'] ) ) ? $report_by_month['total_money_early'] : 0;
			$result['total_money_out_date_in_month_new'] = ( isset( $report_by_month['total_money_out_date'] ) && ! empty( $report_by_month['total_money_out_date'] ) ) ? $report_by_month['total_money_out_date'] : 0;
		}

		return $result;
    }
}
