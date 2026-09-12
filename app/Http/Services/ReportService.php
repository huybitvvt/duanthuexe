<?php

namespace App\Http\Services;

use App\Models\Store;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Models\OrderVehicleDetail;
use App\Models\Order;
use App\Helpers\DateTimeHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\OrderService;
use Illuminate\Support\Facades\Log;
use App\Repositories\OrderRepositoryEloquent;

class ReportService
{
    private $transactionRepository;
	private $orderEloquent;

    public function __construct(TransactionRepository $transactionRepository, OrderService $orderService, OrderRepositoryEloquent $orderEloquent)
    {
        $this->orderService = $orderService;
        $this->transactionRepository = $transactionRepository;
		$this->orderEloquent = $orderEloquent;
    }
    public function getOrderItems(array $params){
        $user  = Auth::user();
        if ($user->role_id === 1 ){
            $store_id = data_get($params, 'store_id');
            
        } else {
            $store_id =   $user->store->id;
        }
    
        $dates = data_get($params, 'dates', []);
        $start_date = data_get($params,'start_date');
        $end_date = data_get($params,'end_date');

        $orderItems =  OrderVehicleDetail::join('orders', 'orders.id', '=', 'order_vehicle_details.order_id')->select('order_vehicle_details.*', 'orders.store_id');
     
        if (!empty($dates)) {
            $startDate = DateTimeHelper::parse($dates[0])->startOfDay();
            $endDate = DateTimeHelper::parse($dates[1])->endOfDay();
            $orderItems->whereBetween('order_vehicle_details.rent_at', [$startDate, $endDate]);
        }
     
        if (!empty($start_date) && !empty($end_date)){
            $orderItems->whereBetween('order_vehicle_details.rent_at' , [
                DateTimeHelper::parse( $start_date)->startOfDay()->toDateTimeString(),
                DateTimeHelper::parse( $end_date)->endOfDay()->toDateTimeString()
            ]);
        } 

        if ($store_id) {
            $orderItems->where('store_id', $store_id);
        }
        return $orderItems->orderBy('order_vehicle_details.id', 'DESC')->get();
    }

	public function getOrderItemsCompleted(array $params){
        $user  = Auth::user();
        if ($user->role_id === 1 ){
            $store_id = data_get($params, 'store_id');
            
        }else{
            $store_id =   $user->store->id;
        }
    
        $dates = data_get($params, 'dates', []);
        $start_date = data_get($params,'start_date');
        $end_date = data_get($params,'end_date');

        $orderItems =  OrderVehicleDetail::join('orders', 'orders.id', '=', 'order_vehicle_details.order_id')->select('order_vehicle_details.*', 'orders.store_id');
     
        if (!empty($dates)) {
            $startDate = DateTimeHelper::parse($dates[0])->startOfDay();
            $endDate = DateTimeHelper::parse($dates[1])->endOfDay();
            $orderItems->whereBetween('order_vehicle_details.completed_at', [$startDate, $endDate]);
        }
     
        if (!empty($start_date) && !empty($end_date)){
            $orderItems->whereBetween('order_vehicle_details.completed_at' , [
                DateTimeHelper::parse( $start_date)->startOfDay()->toDateTimeString(),
                DateTimeHelper::parse( $end_date)->endOfDay()->toDateTimeString()
            ]);
        } 

        if ($store_id) {
            $orderItems->where('store_id', $store_id);
        }
        return $orderItems->orderBy('order_vehicle_details.id', 'DESC')->get();
    }
  

    public function processOrderItems($params) {
        $order_items = $this->getOrderItems($params);
        $totalSum = $order_items->sum(function ($item) {
            return $this->orderService->getOrderItemPrice($item);
        });

        $result = [];
        $result['all_stores']['profit'] =  $totalSum;

        $storeAll = Store::all();
        $stores = $storeAll->mapWithKeys(function ($item, $key) {
            return [$item['id'] => $item['store_name']];
        })->toArray();
    
        $order_item_group = $order_items->groupBy('store_id');
      
        foreach ($order_item_group as $store_id => $items) {
            if (!array_key_exists($store_id, $stores)) {
                continue;
            }
            $store_name = $stores[$store_id];
            
            foreach ($items as $order_item) {
                $rent_at = DateTimeHelper::parse($order_item->rent_at)->format('d/m/Y');
                $completed_at = DateTimeHelper::parse($order_item->completed_at)->format('d/m/Y');
                $hiring_fee = $order_item->handler_price ?: $order_item->total_money;
                $money_out_date = $order_item->money_out_date;

                if (!isset($result[$store_name][$rent_at])) {
					$result[$store_name][$rent_at] = [
						'profit_hiring_fee' => 0,
						'money_out_date' => 0,
						'money_out_date_early' => 0,
						'date' => $rent_at,
					];
                }
               
                if (!isset($result[$store_name][$rent_at]['profit_hiring_fee'])) {
                    $result[$store_name][$rent_at]['profit_hiring_fee'] = 0;
                }
               
                $result[$store_name][$rent_at]['profit_hiring_fee'] += $hiring_fee;
               
                if (!isset($result[$store_name][$completed_at])) {
                    $result[$store_name][$completed_at] = [
                        'money_out_date' => 0,
                        'money_out_date_early' => 0,
                        'date' => $completed_at,
                    ];
                }  
                // if ($money_out_date > 0 && $order_item->completed_at) {
                //     $result[$store_name][$completed_at]['money_out_date'] += $money_out_date;
                // } elseif ($money_out_date < 0 && $order_item->completed_at) {
                //     $result[$store_name][$completed_at]['money_out_date_early'] += $money_out_date;
                // }
            }
        }

		$order_items_completed = $this->getOrderItemsCompleted( $params );
		$order_item_completed_group = $order_items_completed->groupBy('store_id');
		foreach ($order_item_completed_group as $store_id => $items) {
            if (!array_key_exists($store_id, $stores)) {
                continue;
            }
            $store_name = $stores[$store_id];
            
            foreach ($items as $order_item) {
                $completed_at = DateTimeHelper::parse($order_item->completed_at)->format('d/m/Y');
                $money_out_date = $order_item->money_out_date;

                if (!isset($result[$store_name][$completed_at])) {
                    $result[$store_name][$completed_at] = [
                        'money_out_date' => 0,
                        'money_out_date_early' => 0,
                        'date' => $completed_at,
                    ];
                }  
                if ($money_out_date > 0 && $order_item->completed_at) {
                    $result[$store_name][$completed_at]['money_out_date'] += $money_out_date;
                } elseif ($money_out_date < 0 && $order_item->completed_at) {
                    $result[$store_name][$completed_at]['money_out_date_early'] += $money_out_date;
                }
            }
        }
            
        return $result;
    }


    public function getTransactions( $params)
    {    
        $user  = Auth::user();
        if ($user->role_id === 1 ){
            $store_id = data_get($params, 'store_id');
            
        }else{
            $store_id =   $user->store->id;
        }
       
        $dates = data_get($params, 'dates', []);
        $start_date = data_get($params,'start_date');
        $end_date = data_get($params,'end_date');

        $transactions = $this->transactionRepository->select(
            DB::raw('EXTRACT(DAY FROM created_at)::integer as day'),
            DB::raw('EXTRACT(MONTH FROM created_at)::integer as month'),
            DB::raw('EXTRACT(YEAR FROM created_at)::integer as year'),
            'order_id', 'created_at', 'type', 'value', 'user_id', 'store_id','name'
        );
    
        if (!empty($dates)) {
            
            $transactions->whereBetween('created_at', [
                DateTimeHelper::parse($dates[0])->startOfDay()->toDateTimeString(),
                DateTimeHelper::parse($dates[1])->endOfDay()->toDateTimeString()
               
            ]);
        }
        if (!empty($start_date) && !empty($end_date)){
            $transactions->whereBetween('created_at', [
                DateTimeHelper::parse( $start_date)->startOfDay()->toDateTimeString(),
                DateTimeHelper::parse( $end_date)->endOfDay()->toDateTimeString()
            ]);
        }

        if ($store_id) {
            $transactions->where('store_id', $store_id);
        }
        
        return $transactions->orderBy('id', 'DESC')->get();

    }
    public function processTransactions($params){
        $transactions = $this->getTransactions( $params);
        $result = [];
    
        $storeAll = Store::all();
        $keyed = $storeAll->mapWithKeys(function ($item, $key) {
            return [$item['id'] => $item['store_name']];
        })->toArray();
        
        $transaction_group = $transactions->groupBy('store_id');
        
        foreach ($transaction_group as $store_id => $items) {
            if (!array_key_exists($store_id, $keyed)) {
                continue;
            }
            $store_name = $keyed[$store_id];
            
            foreach ($items as $transaction) {
                $key = $transaction->day . '/' . $transaction->month . '/' . $transaction->year;
                $type = $transaction->type;
                $amount = $transaction->value;
            
                if (!isset($result[$store_name][$key])) {
                    $result[$store_name][$key] = [
                        Transaction::THU => 0,
                        Transaction::CHI => 0,
                        'addon' => 0,
                    ];
                }


                
                switch ($type) {
                    case Transaction::CHI:
                        $result[$store_name][$key][Transaction::CHI] = $amount;
                        break;
                    case Transaction::THU:
                        if (strstr($transaction->name,'order:deposit')!==false){
                            $result[$store_name][$key][Transaction::THU] += $amount;
                        }
                        
                        break;
                    case 'addon':
                        $result[$store_name][$key]['addon'] += $amount;
                        break;
                }
                $result[$store_name][$key]['profit_addon'] =   $result[$store_name][$key]['addon'];
                $result[$store_name][$key]['date'] = $key;
                
            }
        
        }
       
        $result['all_stores']['out'] =   $transactions->filter(function ($transaction) {
            return $transaction->type === 'out';
        })->sum('value'); 
        
        $result['all_stores']['addon'] =   $transactions->filter(function ($transaction) {
            return $transaction->type === 'addon';
        })->sum('value');   

        $result['all_stores']['in'] =   $transactions->filter(function ($transaction) {
            return $transaction->type === 'in';
        })->sum('value');   
       
        return $result;
    }

	public function get_new_report(array $params) {

	}

	public function detailReportDayByDay(array $params) {
		$params['separate_result_by_day'] = true;
		return $this->handleDetailReportNew($params);
	}

	public function handleDetailReportNew(array $params) {
		/*
		$order_query = $this->orderEloquent->getModel()->newQuery()->select('orders.id');
		$order_eloquent = $this->orderEloquent->getOrderByParams($order_query, $params);
		$order_ids = $order_eloquent->pluck('id');
		*/
		
		$keyword 		= ( isset( $params['keyword'] ) && ! empty( $params['keyword'] ) ) ? trim( $params['keyword'] ) : '';
		$order_status 	= ( isset( $params['order_status'] ) && ! empty( $params['order_status'] ) ) ? trim( $params['order_status'] ) : '';
		$store_id 		= ( isset( $params['store_id'] ) && is_numeric( $params['store_id'] ) && $params['store_id'] > 0 ) ? intval( $params['store_id'] ) : 0;
		$is_out_of_date = ( isset( $params['is_out_of_date'] ) && ! empty( $params['is_out_of_date'] ) ) ? trim( $params['is_out_of_date'] ) : '';
		$source 		= ( isset( $params['source'] ) && ! empty( $params['source'] ) ) ? $params['source'] : null;
		$start_date 	= ( isset( $params['start_date'] ) && ! empty( $params['start_date'] ) ) ? DateTimeHelper::parse($params['start_date'])->startOfDay()->toDateTimeString() : '';
        $end_date   	= ( isset( $params['end_date'] ) && ! empty( $params['end_date'] ) ) ? DateTimeHelper::parse($params['end_date'])->endOfDay()->toDateTimeString() : '';
		$separate_result_by_day = ( isset( $params['separate_result_by_day'] ) && $params['separate_result_by_day'] ) ? true : false;

		$user  = Auth::user();
        if ($user->role_id !== 1){
            $store_id =   $user->store->id;
        }

		if ( isset( $params['dates'] ) && is_array( $params['dates'] ) && count( $params['dates'] ) == 2 ) {
			$start_date = DateTimeHelper::parse($params['dates'][0])->startOfDay()->toDateTimeString();
			$end_date   = DateTimeHelper::parse($params['dates'][1])->endOfDay()->toDateTimeString();
		}

		/** NEW QUERY */
		$transaction_query = Transaction::query(); // newQuery();
		$order_item_query  = OrderVehicleDetail::query();
		$order_query  = Order::query();
		$search_order_query = Order::query();
		$has_search_order_query = false;

		/** ------ JOIN TABLES ------ */
		if ( ! empty( $keyword ) || ! empty( $store_id ) || ! empty( $order_status ) || ! empty( $is_out_of_date ) || (is_array( $source ) && ! empty( $source )) ) {
			$transaction_query->leftJoin('orders', 'orders.id', '=', 'transactions.order_id');
			$order_item_query->leftJoin('orders', 'orders.id', '=', 'order_vehicle_details.order_id');
		}

		if ( ! empty( $keyword ) ) {
			// $transaction_query->leftJoin('order_vehicle_details', 'order_vehicle_details.order_id', '=', 'orders.id')->leftJoin('vehicles', 'vehicles.id', '=', 'order_vehicle_details.vehicle_id')->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');
			$order_item_query->leftJoin('vehicles', 'vehicles.id', '=', 'order_vehicle_details.vehicle_id')->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');
			$order_query->leftJoin('order_vehicle_details', 'order_vehicle_details.order_id', '=', 'orders.id')->leftJoin('vehicles', 'vehicles.id', '=', 'order_vehicle_details.vehicle_id')->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');

			$search_order_query->leftJoin('order_vehicle_details', 'order_vehicle_details.order_id', '=', 'orders.id')->leftJoin('vehicles', 'vehicles.id', '=', 'order_vehicle_details.vehicle_id')->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');
			$has_search_order_query = true;
		}
		/** ------ END JOIN TABLES ------ */
		if ( ! empty( $keyword ) ) {
			// $transaction_query->where(function ($q) use ($keyword) {
			// 	$q->where('orders.id', is_numeric( $keyword ) ? intval( $keyword ) : substr($keyword, 1))->orWhere('vehicles.license', 'LIKE', "%$keyword%")->orWhere('customers.name', 'LIKE', "%$keyword%")->orWhere('customers.phone', 'LIKE', "%$keyword%");
			// });
			$search_order_query->where(function ($q) use ($keyword) {
				$q->where('orders.id', is_numeric( $keyword ) ? intval( $keyword ) : substr($keyword, 1))->orWhere('vehicles.license', 'LIKE', "%$keyword%")->orWhere('customers.name', 'LIKE', "%$keyword%")->orWhere('customers.phone', 'LIKE', "%$keyword%");
			});
			$has_search_order_query = true;
			$order_item_query->where(function ($q) use ($keyword) {
				$q->where('orders.id', is_numeric( $keyword ) ? intval( $keyword ) : substr($keyword, 1))->orWhere('vehicles.license', 'LIKE', "%$keyword%")->orWhere('customers.name', 'LIKE', "%$keyword%")->orWhere('customers.phone', 'LIKE', "%$keyword%");
			});
			$order_query->where(function ($q) use ($keyword) {
				$q->where('orders.id', is_numeric( $keyword ) ? intval( $keyword ) : substr($keyword, 1))->orWhere('vehicles.license', 'LIKE', "%$keyword%")->orWhere('customers.name', 'LIKE', "%$keyword%")->orWhere('customers.phone', 'LIKE', "%$keyword%");
			});
		}

		if ( ! empty( $order_status ) ) {
			$transaction_query->where('orders.order_status', $order_status );
			$order_item_query->where('orders.order_status', $order_status );
			$order_query->where('orders.order_status', $order_status );
		}

		if ( ! empty( $is_out_of_date ) ) {
			$checking_value = 0;
            if ( $is_out_of_date == 'expired-gt-3days' ) {
                $checking_value = 3 * 24 * 60;
            } else if ( $is_out_of_date == 'expired-gt-10days' ) {
                $checking_value = 10 * 24 * 60;
            }

			$transaction_query->where('orders.out_dated_at', '>', $checking_value);
			$order_item_query->where('orders.out_dated_at', '>', $checking_value);
			$order_query->where('orders.out_dated_at', '>', $checking_value);
        }

		if ( is_array( $source ) && ! empty( $source ) ) {
			$transaction_query->leftJoin('leads', 'leads.order_id', '=', 'orders.id');
			$order_item_query->leftJoin('leads', 'leads.order_id', '=', 'orders.id');
			$order_query->leftJoin('leads', 'leads.order_id', '=', 'orders.id');

            if ( count( $source ) > 0 ) {
                if (in_array('NULL', $source)) {
                    $transaction_query->where(function($query) use ($source) {
                        $query->whereIn('leads.user_id', array_diff($source, ['NULL']))->orWhereNull('leads.user_id');
                    });
					$order_item_query->where(function($query) use ($source) {
                        $query->whereIn('leads.user_id', array_diff($source, ['NULL']))->orWhereNull('leads.user_id');
                    });
					$order_query->where(function($query) use ($source) {
                        $query->whereIn('leads.user_id', array_diff($source, ['NULL']))->orWhereNull('leads.user_id');
                    });
                } else {
                    $transaction_query->whereIn('leads.user_id', $source);
                    $order_item_query->whereIn('leads.user_id', $source);
                    $order_query->whereIn('leads.user_id', $source);
                }
            } else {
                $transaction_query->whereNull('leads.user_id');
                $order_item_query->whereNull('leads.user_id');
                $order_query->whereNull('leads.user_id');
            }
        }

		$user = auth()->user();
		if ( 0 == $store_id && $user->role_rel->slug !== 'quan-tri-vien') {
			$store_id = $user->store_id;
		}
        if ($store_id > 0) {
			$transaction_query->where('orders.store_id', $store_id);
			$order_item_query->where('orders.store_id', $store_id);
			$order_query->where('orders.store_id', $store_id);
		}

		if ( ! empty( $start_date ) ) {
			$transaction_query->where('transactions.created_at', '>=', $start_date);
			$order_item_query->where('order_vehicle_details.completed_at', '>=', $start_date);
			$order_query->where('orders.completed_at', '>=', $start_date); // Check case using field orders.created_at
		}

		if ( ! empty( $end_date ) ) {
			$transaction_query->where('transactions.created_at', '<=', $end_date);
			$order_item_query->where('order_vehicle_details.completed_at', '<=', $end_date);
			$order_query->where('orders.completed_at', '<=', $end_date); // Check case using field orders.created_at
		}

		if ( $has_search_order_query ) {
			$search_order_ids = $search_order_query->pluck('orders.id');
			$transaction_query->whereIn('orders.id', $search_order_ids);
		}

		$total_deposit_query 	= clone $transaction_query;
		$total_renew_new_query 	= clone $transaction_query;
		$total_rental_fees_query = clone $transaction_query;
		$total_refund_query = clone $transaction_query;
		$total_money_early_query = clone $order_item_query;
		$total_money_out_date_query = clone $order_item_query;
		$total_origin_refund_query = clone $order_query;

		$total_deposit_query->where('transactions.type', 'in')->where(function ($query) {
			$query->where('transactions.name', 'LIKE', "order:deposit%")->orWhere('transactions.name', 'order:deposit:keep_vehicle')->orWhere('transactions.name', 'order:additional_deposit');
		});

		$total_renew_new_query->where('transactions.name', 'addon');
		$total_rental_fees_query->where('transactions.name', 'order:rental_fees')->where('transactions.type', 'in');
		$total_refund_query->where("transactions.type", "out")->where('transactions.name', 'LIKE', "order:complete%");

		$total_money_early_query->where('order_vehicle_details.handler_price', '=', 0)->where('order_vehicle_details.money_out_date', '<', 0);
		$total_money_out_date_query->where('order_vehicle_details.handler_price', '=', 0)->where('order_vehicle_details.money_out_date', '>', 0);
		
		$total_origin_refund_query->where('orders.order_status', 'completed'); // Tổng số tiền cần refund mà chưa tính phí quá hạn hay trả sớm. VD hợp đồng A khách cọc 1tr thì khoản origin-refund phải là 1tr. Trên thực tế nếu phát sinh trả sớm hoặc trả muộn thì sẽ cộng trừ vào khoản cọc này.

		// Log::info('Generated SQL:', ['total_money_out_date_query' => $total_money_out_date_query->toSql(), 'total_money_out_date_bindings' => $total_money_out_date_query->getBindings()]);
		// Log::info('Generated SQL:', ['total_money_early_query' => $total_money_early_query->toSql(), 'total_money_early_bindings' => $total_money_early_query->getBindings()]);


		$found_order_ids = $total_origin_refund_query->pluck('orders.id');
		$total_origin_refund_query2  = Order::query()->whereIn('orders.id', $found_order_ids);


		$total_deposit_query_str = vsprintf(str_replace('?', "'%s'", $total_deposit_query->toSql()), $total_deposit_query->getBindings());
		Log::info('total_deposit_query sql: ', [ 'query' => $total_deposit_query_str ]);

		if ( $separate_result_by_day ) {
			return [
				'total_deposit' 		=> $this->orderByTransactionDate($total_deposit_query)->get(),
				'total_renew'   		=> $this->orderByTransactionDate($total_renew_new_query)->get(),
				'total_rental_fees' 	=> $this->orderByTransactionDate($total_rental_fees_query)->get(),
				'total_real_refund' 	=> $this->orderByTransactionDate($total_refund_query)->get(),
				
				// 'total_origin_refund' 	=> $this->orderByOrderCompletedDate($total_origin_refund_query)->get(),
				'total_origin_refund' 	=> $this->orderByOrderCompletedDate($total_origin_refund_query2)->get(),
				
				'total_money_early' 	=> $this->orderByOrderItemDate($total_money_early_query)->get(),
				'total_money_out_date' 	=> $this->orderByOrderItemDate($total_money_out_date_query)->get(),
			];
		} else {
			return [
				/** NEW QUERY */
				// Tổng thu cọc: name in( 'order:deposit:keep_vehicle', 'order:additional_deposit', 'order:deposit:11414' ) && type = in
				'total_deposit' 		=> $total_deposit_query->sum('value'),
				// Tổng thu gia hạn: name = addon && type = addon
				'total_renew' 			=> $total_renew_new_query->sum('value'),
				// Tổng thu phí thuê: name = order:rental_fees && type = in
				'total_rental_fees' 	=> $total_rental_fees_query->sum('value'),
	
				// Tổng số tiền cọc đáng ra phải hoàn trả: chưa cộng trừ thêm khoản trả sớm, trả muộn.
				// 'total_origin_refund' 	=> $total_origin_refund_query->select(DB::raw('SUM(COALESCE(first_deposit_amount, 0) + COALESCE(additional_deposit_amount, 0)) as total_sum'))->value('total_sum'),
				'total_origin_refund' 	=> $total_origin_refund_query2->select(DB::raw('SUM(COALESCE(first_deposit_amount, 0) + COALESCE(additional_deposit_amount, 0)) as total_sum'))->value('total_sum'),
				// Tổng trả cọc: name like 'order:complete:11415' && type = out
				'total_real_refund' 	=> $total_refund_query->sum('value'),
				//-- Tổng trả sớm: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date < 0.
				'total_money_early' 	=> $total_money_early_query->sum('order_vehicle_details.money_out_date'),
				//-- Tổng phạt muộn: order_vehicle_details join orders => handler_price = 0 && orders.status = 'completed' && minute_out_date > 0.
				'total_money_out_date' 	=> $total_money_out_date_query->sum('order_vehicle_details.money_out_date'),
			];
		}
	}

	public function orderByTransactionDate(&$query) {
		return $query->select(DB::raw('DATE(transactions.created_at) as date'), DB::raw('SUM(value) as total_value'))->groupBy(DB::raw('DATE(transactions.created_at)'));
	}

	public function orderByOrderItemDate(&$query) {
		return $query->select(DB::raw('DATE(order_vehicle_details.completed_at) as date'), DB::raw('SUM(order_vehicle_details.money_out_date) as total_value'))->groupBy(DB::raw('DATE(order_vehicle_details.completed_at)'));
	}

	public function orderByOrderCompletedDate(&$query) {
		return $query->select(DB::raw('DATE(orders.completed_at) as date'), DB::raw('SUM(COALESCE(first_deposit_amount, 0) + COALESCE(additional_deposit_amount, 0)) as total_value') )->groupBy(DB::raw('DATE(orders.completed_at)'));
	}

    public function handleDetailReport(array $params)
    {
       $listOrderItems = $this->processOrderItems($params);
       $listTransactions =  $this->processTransactions($params);
     
       $mergedArray = [];
       $all_stores= array_merge( $listOrderItems['all_stores'],$listTransactions['all_stores']);
       $all_stores['total_hiring_money'] = $all_stores['profit'] + $all_stores['addon'];
    
       unset($listOrderItems['all_stores']);
    
       unset($listTransactions['all_stores']);

        foreach ([$listOrderItems, $listTransactions] as $array) {
            foreach ($array as $key => $value) {
                if (!isset($mergedArray[$key])) {
                    $mergedArray[$key] = $value;
                } else {
                    foreach ($value as $date => $data) {
                        if (isset($mergedArray[$key][$date])) {
                            $mergedArray[$key][$date] = array_merge($mergedArray[$key][$date], $data);
                        } else {
                            $mergedArray[$key][$date] = $data;
                        }
                    }
                }
            }
        }
        foreach ($mergedArray as &$store) {
            foreach ($store as &$item) {
                if (!isset($item['profit_addon'])) {
                    $item['profit_addon'] = 0;
                } 
                if (!isset($item['profit_hiring_fee'])) {
                    $item['profit_hiring_fee'] = 0;
                }
                if (!isset($item['money_out_date'])) {
                    $item['money_out_date'] = 0;
                }
                if (!isset($item['money_out_date_early'])) {
                    $item['money_out_date_early'] = 0;
                }
                $item['profit'] = $item['profit_addon'] + $item['profit_hiring_fee'] + $item['money_out_date'] + $item['money_out_date_early'];
            }
        }

        $all_stores  = [];
 
        foreach ($mergedArray as $location => $dates) {
            foreach ($dates as $date => $values) {
                if (!isset($all_stores[$date])) {
                    $all_stores[$date] = [
                        "in" => 0,
                        "out" => 0,
                        "addon" => 0,
                        "profit_addon" => 0,
                        "date" => $date,
                        "profit_hiring_fee" => 0,
                        "profit" => 0,
                        "money_out_date" => 0,
                        "money_out_date_early" => 0
                    ];
                }
        
                // Check if the 'in' key exists before accessing it
                $all_stores[$date]["in"] += isset($values["in"]) ? $values["in"] : 0;
                $all_stores[$date]["out"] += isset($values["out"]) ? $values["out"] : 0;
                $all_stores[$date]["addon"] += isset($values["addon"]) ? $values["addon"] : 0;
                $all_stores[$date]["profit_addon"] += isset($values["profit_addon"]) ? $values["profit_addon"] : 0;
                $all_stores[$date]["profit_hiring_fee"] += isset($values["profit_hiring_fee"]) ? $values["profit_hiring_fee"] : 0;
                $all_stores[$date]["profit"] += isset($values["profit"]) ? $values["profit"] : 0;
                $all_stores[$date]["money_out_date"] += isset($values["money_out_date"]) ? $values["money_out_date"] : 0;
                $all_stores[$date]["money_out_date_early"] += isset($values["money_out_date_early"]) ? $values["money_out_date_early"] : 0;
            }
        }
 
        

        $all_stores_all_dates = [
            "in" => 0,
            "out" => 0,
            "addon" => 0,
            "profit_addon" => 0,
            "profit_hiring_fee" => 0,
            "profit" => 0,
            "money_out_date" => 0,
            "money_out_date_early" => 0
        ];
        
        foreach ($all_stores as $day) {
        
            $all_stores_all_dates["in"] += isset($day["in"]) ? $day["in"] : 0;
            $all_stores_all_dates["out"] += isset($day["out"]) ? $day["out"] : 0;
            $all_stores_all_dates["addon"] += isset($day["addon"]) ? $day["addon"] : 0;
            $all_stores_all_dates["profit_addon"] += isset($day["profit_addon"]) ? $day["profit_addon"] : 0;
            $all_stores_all_dates["profit_hiring_fee"] += isset($day["profit_hiring_fee"]) ? $day["profit_hiring_fee"] : 0;
            $all_stores_all_dates["profit"] += isset($day["profit"]) ? $day["profit"] : 0;
            $all_stores_all_dates["money_out_date"] += isset($day["money_out_date"]) ? $day["money_out_date"] : 0;
            $all_stores_all_dates["money_out_date_early"] += isset($day["money_out_date_early"]) ? $day["money_out_date_early"] : 0;
        }


        $mergedArray['all_stores'][0]  = $all_stores_all_dates; 
       
        return $mergedArray;
     
    }
    public function alignColumns($data,$columnOrder){
        $reorderedData = [];
        foreach ($data as $row) {
          
            $filledRow = [];
            foreach ($columnOrder as $column) {
                $filledRow[$column] = $row[$column] ?? 0;
            }
            $reorderedData[] = $filledRow;
        }
        return $reorderedData;
    }

    
     
}
