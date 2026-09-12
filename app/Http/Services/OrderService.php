<?php


namespace App\Http\Services;

use App\Models\Transaction;
use App\Models\Lead;
use App\Http\Services\TransactionService;
use App\Repositories\TransactionRepository;
use App\Entities\Customer;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Helpers\CarRentalHelper;
use App\Http\Controllers\Transaction\TransactionController;
use App\Models\Vehicle;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AddOnOrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\VehicleRepository;
use App\Validators\OrderValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\OrderVehicleDetailRepositoryEloquent;
use App\Exceptions\CustomException;
use App\Models\Cash;
use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderService
{
    protected $orderRepository;
    protected $customerRepository;
    protected $transactionRepository;
    protected $activityLogRepository;
    protected $vehicleRepository;
    protected $addOnOrderRepository;
    protected $transactionService;
 
    public function __construct(
        OrderRepository       $orderRepository,
        CustomerRepository    $customerRepository,
        TransactionRepository $transactionRepository,
        ActivityLogRepository $activityLogRepository,
        VehicleRepository     $vehicleRepository,
        AddOnOrderRepository  $addOnOrderRepository,
        TransactionService $transactionService
    )
    {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->transactionRepository = $transactionRepository;
        $this->activityLogRepository = $activityLogRepository;
        $this->vehicleRepository = $vehicleRepository;
        $this->addOnOrderRepository = $addOnOrderRepository;
        $this->transactionService = $transactionService;
    }

    public function index(Request $request)
    {
        return $this->orderRepository->index($request);
    }

    public function store(Request $request)
    {
        $customer = $this->storeOrUpdateCustomer($request);
        $order = $this->updateOrCreateOrder($request, null, $customer);
        $this->updateVehicles($request, $order);
        $this->createFirstDeposit($request, $order);
        if ($request->has('leads') && isset($request->leads)){
            $this->updateLeads($request->get('leads'),$order->id);
        }
		$this->saveOrderLog($request, $order, true);
    }

    public function update(Request $request , Order $order )
    {
        $customer = $this->storeOrUpdateCustomer($request, $order);

        $this->updateOrCreateOrder($request, $order, null);

        $this->saveOrderLog($request, $order);
        
        $this->updateTransactions($request, $order);
        $transaction_ids_to_destroy = $request->get('transaction_ids_to_destroy');
        foreach ($transaction_ids_to_destroy as $id){
            $deleted = Transaction::destroy($id);        
        }
        
		$this->updateVehicles($request, $order);
        $this->updateFee( $request, $order );
		$this->updateOrderVehicleDetails($request, $order);

        if ($request->has('leads') && isset($request->leads)){
            $this->updateLeads($request->get('leads'),$order->id);
        }
    }
	private function maybeUpdateOdometer( $request ) {
		$request_items = $request->get('order_items');
		foreach ( $request_items as $item ) {
			$item_data = [];
			if ( isset( $item['odometer_before'] ) && is_numeric( $item['odometer_before'] ) ) {
				$item_data['odometer_before'] = intval( $item['odometer_before'] );
			}
			if ( isset( $item['odometer_after'] ) && is_numeric( $item['odometer_after'] ) ) {
				$item_data['odometer_after'] = intval( $item['odometer_after'] );
			}
			if ( ! empty( $item_data ) ) {
				$order_vehicle_detail = OrderVehicleDetail::find( $item['id'] );
				if ( $order_vehicle_detail ) {
					$updated = $order_vehicle_detail->update( $item_data );
				}
			}
		}
	}
    private function updateLeads($leads, $order_id){
        foreach ($leads as $id){
            $lead = Lead::find($id);
            $lead->update(['order_id'=> $order_id,'status'=>'completed']);
        }
    }
    private function updateOrderVehicleDetails($request, $order){
        $items = $order->orderItems->toArray() ;
        
        if ($request->get('order_status') == 'bad_debt'){
           $order->customer()->update(['warning'=>'Khách hàng có nợ xấu ở order số '.$order->id ]);

            foreach ($items as $item){
                $order_vehicle_detail = OrderVehicleDetail::find($item['id']);
                $updated = $order_vehicle_detail->update([
                    'money_out_date' => 0,
                    'minute_out_date' => 0,
                ]); 
            }
        }
    }
 
    protected function updateOrCreateOrder(Request $request, Order $order = null, Customer $customer = null)
	{
		$dataOrder = [
			'store_id' => $request->get('store_id'),
			'total' => $request->get('total'),
			'pid' => $request->get('pid'),
			'note' => $request->get('note'),
			'note_payment' => $request->get('note_item'),
		];

		// If $order is null, create a new order; otherwise, update the existing order
		if ($order === null) {
			$created_at = DateTimeHelper::parse($request->get('created_at'));

			$dataOrder['customer_id'] = $customer->id;
			$dataOrder['order_type'] = OrderValidator::ORDER_TYPE_RENTING;
			$dataOrder['order_status'] = OrderValidator::ORDER_RENTING;
			$dataOrder['data_version'] = 2;
			$dataOrder['created_at'] = $created_at;

			$get_contract_type = $request->get('contract_type');
			$contract_type = 1;
			if ( is_numeric( $get_contract_type ) ) {
				$contract_type = intval( $get_contract_type );
			}
			if ( $contract_type == 2 ) {
				$dataOrder['order_status'] = OrderValidator::ORDER_DEPOSIT_CONTRACT;
				$dataOrder['deposit_contract_created_at'] = DateTimeHelper::now();
				$dataOrder['total'] = 0;
				$dataOrder['pid'] = 0;
			}

			return $this->orderRepository->store($dataOrder);
		} else { // update existing order
			$additional_deposit_amount = $request->get('additional_deposit_amount');
			// $dataOrder['order_status'] = OrderValidator::ORDER_RENTING; -- Remove because this will update complete order to renting

			// first_deposit_amount
		
			if ($request->get('order_status') == 'bad_debt'){ 
				// if order is marked as bad_debt, change status to bad_debt
			
				$dataOrder['order_status'] = OrderValidator::ORDER_BAD_DEBT;
				$dataOrder['out_dated_at'] = 0;
				$dataOrder['total'] =  $this->calTotalWithNoOutdate($order);
			} 

			// Add this case to transactions table
			// if (is_numeric( $additional_deposit_amount ) && $additional_deposit_amount > 0) {
			// 	$dataOrder['pid'] = $order->pid + $additional_deposit_amount;
			// }

			if ($request->get('editing_order_created_at')) { // Allow change created_at for this order before update type from deposit_contract to normal order in (Mock: Data 02)
				$created_at = DateTimeHelper::parse($request->get('created_at'));
				if ($created_at) {
					$dataOrder['created_at'] = $created_at;
				}
			}

			if ($request->get('order_status') == 'deposit_contract' && $request->get('start_this_contract')) { // Mock: Data 02
				$dataOrder['created_at'] = DateTimeHelper::now();
				$dataOrder['updated_at'] = DateTimeHelper::now();
				$dataOrder['order_status'] = OrderValidator::ORDER_RENTING; // Start contract so change this order status to ORDER_RENTING
			}

			$res = $order->update($dataOrder);
		}
	}
	protected function updateTransactions(Request $request, Order $order)
	{
		$transactions = $request->get('transactions');   

		if ( $transactions ) {
			// $this->updateTransaction($transactions,'deposit',$request->store_id);
			// $this->updateTransactionComplete($transactions, $request->debt);
			$this->maybeUpdateTransactions($request, $order);
		} else {
			$this->createFirstDeposit($request,$order);
		}
	}

	protected function maybeUpdateTransactions( Request $request, Order $order ) {
		$order_id = $order->id;
		$db_order = Order::find($order_id);
		
		if ( $db_order ) {
			$decode_first_deposit = unserialize( $db_order->first_deposit_payment_method );
			$decode_rental_fee = unserialize( $db_order->total_rental_payment_method );
			$decode_additional_deposit = unserialize( $db_order->additional_deposit_payment_method );

			if ( is_array( $decode_first_deposit ) && isset( $decode_first_deposit['payment_method'] ) ) {
				$decode_first_deposit = $decode_first_deposit['payment_method'];
			}
			if ( is_array( $decode_rental_fee ) && isset( $decode_rental_fee['payment_method'] ) ) {
				$decode_rental_fee = $decode_rental_fee['payment_method'];
			}
			if ( is_array( $decode_additional_deposit ) && isset( $decode_additional_deposit['payment_method'] ) ) {
				$decode_additional_deposit = $decode_additional_deposit['payment_method'];
			}

			$input_first_deposit = $request->get('first_deposit_payment_method');
			$input_rental_fee = $request->get('total_rental_payment_method');
			$input_additional_deposit = $request->get('additional_deposit_payment_method');
			

			if ( is_numeric( $request->first_deposit_amount ) && $request->first_deposit_amount > 0 ) { // New deposit amount diff with the db data.
				if ( $db_order->first_deposit_amount != $request->first_deposit_amount || ( $decode_first_deposit && $decode_first_deposit['payment_method'] != $input_first_deposit['payment_method'] ) ) {
					$db_deposit_transaction_ids = Transaction::where('order_id', $order_id)->where(function ($query) use ($order_id) {
						$query->where('name', "order:deposit:$order_id")->orWhere('name', 'order:deposit:keep_vehicle');
					})->pluck('id');

					if ( $db_deposit_transaction_ids ) {
						Transaction::destroy($db_deposit_transaction_ids);
					}
					$this->maybeCreateOrderFirstDeposit($request, $db_order, true);
				} else {
					$this->maybeUpdateTransactionCreatedAt($request, $order, 'first_deposit_amount');
				}
			}

			if ( is_numeric( $request->additional_deposit_amount ) && $request->additional_deposit_amount > 0 ) { // New deposit amount diff with the db data.
				if ( $db_order->additional_deposit_amount != $request->additional_deposit_amount || ( $decode_additional_deposit && $decode_additional_deposit['payment_method'] != $input_additional_deposit['payment_method'] ) ) {
					$db_deposit_transaction_ids = Transaction::where('name', "order:additional_deposit")->where('order_id', $order_id)->pluck('id');
					if ( $db_deposit_transaction_ids ) {
						Transaction::destroy($db_deposit_transaction_ids);
					}
					$this->maybeCreateOrderAdditionalDeposit($request, $db_order, true);
				} else {
					$this->maybeUpdateTransactionCreatedAt($request, $order, 'additional_deposit_amount');
				}
			}

			if ( is_numeric( $request->total_rental_fees ) && $request->total_rental_fees > 0 ) { // New rental fees amount diff with the db data.
				if ( $db_order->total_rental_fees != $request->total_rental_fees || ( $decode_rental_fee && $decode_rental_fee['payment_method'] != $input_rental_fee['payment_method'] ) ) {
					$db_rental_fees_transaction_ids = Transaction::where('name', 'order:rental_fees')->where('order_id', $order_id)->pluck('id');
					
					if ( $db_rental_fees_transaction_ids ) {
						Transaction::destroy($db_rental_fees_transaction_ids);
					}
					$this->maybeCreateOrderRentalFee($request, $db_order, true);
				} else {
					$this->maybeUpdateTransactionCreatedAt($request, $order, 'total_rental_fees');
				}
			}
		}
	}

	protected function maybeUpdateTransactionCreatedAt(Request $request, Order $order, $type = '') {
		if ($request->get('editing_order_created_at')) { // Allow change created_at for this order before update type from deposit_contract to normal order in (Mock: Data 02)
			$created_at_str = $request->get('created_at');
			$created_at = DateTimeHelper::parse($created_at_str);
			if ($created_at) {
				$order_id = $order->id;
				$db_transactions = [];

				if ( 'first_deposit_amount' == $type ) {
					$db_transactions = Transaction::where('order_id', $order_id)->where(function ($query) use ($order_id) {
						$query->where('name', "order:deposit:$order_id")->orWhere('name', 'order:deposit:keep_vehicle');
					})->pluck('created_at', 'id');
				} else if ( 'additional_deposit_amount' == $type ) {
					$db_transactions = Transaction::where('name', "order:additional_deposit")->where('order_id', $order_id)->pluck('created_at', 'id');
				} else if ( 'total_rental_fees' == $type ) {
					$db_transactions = Transaction::where('name', 'order:rental_fees')->where('order_id', $order_id)->pluck('created_at', 'id');
				}
				if ($db_transactions->isNotEmpty()) {
					foreach ( $db_transactions as $id => $db_created_at ) {
						$transaction = Transaction::find($id);
						if ( $transaction ) {
							$transaction->update(['created_at' => $created_at]);

							$log = new ActivityLog();
							$log->name = 'transaction:' . $id;
							$log->order_id = $order_id;
							$log->user_id = Auth::id();
							$log->action = 'update';
							$log->content = 'Giao dịch #' . $id . ', sửa ngày giao dịch: ' . $db_created_at->format('d-m-Y H:i:s') . ' -> ' . $created_at_str;
							
							$transaction->update(['created_at' => $created_at]);
							$log->save();

						}
						
					}
					
				}
			}
		}
	}

	protected function updateTransaction($transactions, $transaction_keyword, $store_id){
		$arr = array_values(array_filter($transactions, function ($item) use ($transaction_keyword){
			return strstr($item['name'], $transaction_keyword) !== false;
			}));
		$tran = !empty($arr) ? $arr[0] : null;
		if ($tran){        
			$result = $this->transactionService->processPaymentMethod($tran['payment_method'],$tran['bank_id'], $store_id);
			$dataTransaction = [
					'payment_method' => $tran['payment_method'],
					'bank_id' =>    $result['bank_id'],
					'cash_id' => $result['cash_id'],
					'value'=>$tran['value'],
			];
				
			$this->transactionRepository->update($dataTransaction,$tran['id']);
		}
	}
	protected function updateTransactionComplete($transactions, $value){
		$arr = array_values(array_filter($transactions, function ($item) {
			return strstr($item['name'], 'complete') !== false;
			}));
		$tran = !empty($arr) ? $arr[0] : null;
		if ($tran){        
			$dataTransaction = [
					'value'=> $value,
			];
				
			$this->transactionRepository->update($dataTransaction,$tran['id']);
		}
	}
	protected function updateVehicles(Request $request, Order $order)
	{
		$order_items = $request->get('order_items');
		$sync_data = [];
		$money_outdate = 0;
		$need_release_vehicle_ids = [];

		foreach ($order_items as $order_item) {
			$vehicle_id = $order_item['vehicle_id'];
			if ( isset( $order_item['vehicle'] ) ) {
				$old_vehicle = $order_item['vehicle'];
				if ( $old_vehicle && isset( $old_vehicle['id'] ) && $vehicle_id != $old_vehicle['id'] ) { // The vehicle id is changed, so need to release this vehicle.
					$need_release_vehicle_ids[] = $old_vehicle['id'];
				}
			}

			if ($request->get('order_status') == 'bad_debt'){
				Vehicle::where('id', $vehicle_id)->update(['status' => Vehicle::STATUS_BAD_DEBT]);
			} else {
				if ( $request->get('order_status') != 'completed' ) {
					Vehicle::where('id', $vehicle_id)->update(['status' => Vehicle::STATUS_USING]);
				}
			}

			$total_money = data_get($order_item, 'total_money', 0);
			$custom_total_money = data_get($order_item, 'custom_total_money', 0);

			$hiring_fee = $total_money;
			if ( is_numeric( $custom_total_money ) && $custom_total_money > 0 ) {
				$hiring_fee = $custom_total_money;
			}

			$item_sync_data = [
				'price_id' => data_get($order_item, 'price_id', 0),
				'rent_at' => DateTimeHelper::parse($order_item['rent_at']),
				'return_at' => DateTimeHelper::parse($order_item['return_at']),
				'total_money' => $total_money,
				'borrow_hats' => $order_item['borrow_hats'],
				'type' => $order_item['type'] ?? 'total',
				'handler_price' => $order_item['handler_price'] ?? 0,
				'substitute_unit_price' => $order_item['substitute_unit_price'],
				'hiring_fee' => $hiring_fee,
			];

			if ( isset( $order_item['odometer_before'] ) && is_numeric( $order_item['odometer_before'] ) ) {
				$item_sync_data['odometer_before'] = intval( $order_item['odometer_before'] );
			}
			if ( isset( $order_item['odometer_after'] ) && is_numeric( $order_item['odometer_after'] ) ) {
				$item_sync_data['odometer_after'] = intval( $order_item['odometer_after'] );
			}

			if ( isset( $order_item['money_out_date'] ) && is_numeric( $order_item['money_out_date'] ) ) {
				$item_sync_data['money_out_date'] = $order_item['money_out_date'];
				$money_outdate = $money_outdate + $order_item['money_out_date'];
			}

			$sync_data[$vehicle_id] = $item_sync_data;
		}

		$order->vehicles()->sync($sync_data);
		if ( $money_outdate !== 0 ) {
			$order->update( [ 'outdate_or_early_amount' => $money_outdate ] );
		}

		if ( ! empty( $need_release_vehicle_ids ) ) {
			$this->vehicleRepository->whereIn('id', $need_release_vehicle_ids)->update(['status' => Vehicle::STATUS_READY]);
		}
	}
	protected function storeOrUpdateCustomer(Request $request, Order $order = null)
	{
		$warning = $request->get('warning');
	
		$dataCustomer = [
			'name' => $request->get('customer_name'),
			'phone' => $request->get('customer_phone'),
			'address' => $request->get('customer_address'),
			'id_card' => $request->get('customer_id_card'),
			'warning' => $warning,
			'status' => $warning ? Customer::STATUS_WARNING : Customer::STATUS_ACTIVE,
		];
	
		if ($order) {
			$order->customer()->update($dataCustomer);
			return $order->customer;
		}
	
		$customer = Customer::query()->where('id_card', $request->get('customer_id_card'))->first();
		if (!$customer) {
			$customer = $this->customerRepository->skipPresenter()->create($dataCustomer);
		} else {
			$customer->update($dataCustomer);
		}
	
		return $customer;
	}

	public function maybeCreateOrderRentalFee($request, $order, $is_updating_old_record = false) {
		if ( $is_updating_old_record && is_null( $order->data_version ) ) { // This order is old version so don't update the rental fees.
			return;
		}

		$total_rental_fees = $request->get('total_rental_fees');
		$total_rental_payment_method = $request->get('total_rental_payment_method');

		$get_contract_type = $request->get('contract_type');
		$contract_type = 1;
		if ( is_numeric( $get_contract_type ) && $get_contract_type > 1 ) {
			$contract_type = intval( $get_contract_type );
		}
		$order_id = $order->id;
		$store_id = $request->store_id;
		$rental_fee_transaction_ids = array();

		
		if ( 1 == $contract_type ) { // Only save fee for new order, not deposit contract.
			if ( is_numeric( $total_rental_fees ) && $total_rental_fees > 0 ) {
				if ( is_array( $total_rental_payment_method ) && isset( $total_rental_payment_method['payment_method'] ) ) {
					$record_name = 'order:rental_fees';
					$object_name = 'rental_fees';

					$rental_bank_amount = $total_rental_payment_method['bank_transfer_amount'];
					$rental_cash_amount = $total_rental_payment_method['cash_amount'];
					$rental_payment_method = $total_rental_payment_method['payment_method'];
					$rental_bank_id = $total_rental_payment_method['bank_id'];
					$get_rental_payment = $this->transactionService->processPaymentMethod( $rental_payment_method, $rental_bank_id, $store_id );
					$records = [];

					if ( 3 == $rental_payment_method ) {
						if ( is_numeric( $rental_bank_amount ) && $rental_bank_amount > 0 ) {
							$records[] = [
								'name' => $record_name,
								'type' => 'in',
								'value' => $rental_bank_amount,
								'note' => 'Thu phí thuê xe, hợp đồng ' . $order_id . ' thông qua chuyển khoản',
								'user_id' => Auth::id(),
								'order_id' => $order_id,
								'store_id' => $store_id,
								'bank_id'  => $rental_bank_id,
								'cash_id'  => null,
								'payment_method' => $rental_payment_method,
								'object_name' => $object_name,
							];
						}
						if ( is_numeric( $rental_cash_amount ) && $rental_cash_amount > 0 ) {
							$records[] = [
								'name' => $record_name,
								'type' => 'in',
								'value' => $rental_cash_amount,
								'note' => 'Thu phí thuê xe, hợp đồng ' . $order_id . ' thông qua tiền mặt',
								'user_id' => Auth::id(),
								'order_id' => $order_id,
								'store_id' => $store_id,
								'bank_id'  => null,
								'cash_id'  => $get_rental_payment['cash_id'],
								'payment_method' => $rental_payment_method,
								'object_name' => $object_name,
							];
						}
					} else {
						$records[] = [
							'name' => $record_name,
							'type' => 'in',
							'value' => $total_rental_fees,
							'note' => 'Thu phí thuê xe, hợp đồng ' . $order_id,
							'user_id' => Auth::id(),
							'order_id' => $order_id,
							'store_id' => $store_id,
							'bank_id' => $get_rental_payment['bank_id'],
							'cash_id' => $get_rental_payment['cash_id'],
							'payment_method' => $rental_payment_method,
							'object_name' => $object_name,
						];
					}

					if ( ! empty( $records ) ) {
						foreach ( $records as $record ) {
							if ( $is_updating_old_record ) {
								if ( $order->created_at ) {
									$record['created_at'] = $order->created_at->format('Y-m-d H:i:s');
								}
							}
							if ( ! $is_updating_old_record && $request->get('created_at') ) {
								$record['created_at'] = DateTimeHelper::parse($request->get('created_at'));
							}
							$created = $this->transactionRepository->create( $record );
							if ( $created && $created->id ) {
								$rental_fee_transaction_ids[] = $created->id;
							}
						}
					}

					$order_update_data = [ 'total_rental_fees' => $total_rental_fees ];
					$transaction_details = [
						'payment_method' => $total_rental_payment_method
					];

					if ( ! empty( $rental_fee_transaction_ids ) ) {
						$transaction_details['row_ids'] = $rental_fee_transaction_ids;
					}
					$order_update_data['total_rental_payment_method'] = serialize( $transaction_details );
					$order->update( $order_update_data );
				}
			}
		}		
	}

	public function maybeCreateOrderFirstDeposit($request, $order, $is_updating_old_record = false) {
		$firt_deposit_amount = data_get( $request, 'first_deposit_amount', 0 );
		$firt_deposit_payment_method = $request->get('first_deposit_payment_method');

		$get_contract_type = $request->get('contract_type');
		$contract_type = 1;
		if ( is_numeric( $get_contract_type ) ) {
			$contract_type = intval( $get_contract_type );
		}
		$order_id = $order->id;
		$store_id = $request->store_id;
		$first_deposit_transaction_ids = array();

		if ( is_numeric( $firt_deposit_amount ) && $firt_deposit_amount > 0 ) {
			if ( is_array( $firt_deposit_payment_method ) && isset( $firt_deposit_payment_method['payment_method'] ) ) {
				$record_name = 'order:deposit:' . $order_id;
				if ( $contract_type == 2 || $order->deposit_contract_created_at ) { // Khi tạo mới hợp đồng cọc thì $contract_type == 2, khi đã kích hoạt hợp đồng cọc rồi thì $order->deposit_contract_created_at != null.
					$record_name = 'order:deposit:keep_vehicle';
				}
				$object_name = 'first_deposit';

				$deposit_bank_amount = $firt_deposit_payment_method['bank_transfer_amount'];
				$deposit_cash_amount = $firt_deposit_payment_method['cash_amount'];
				$deposit_payment_method = $firt_deposit_payment_method['payment_method'];
				$deposit_bank_id = $firt_deposit_payment_method['bank_id'];
				$get_deposit_payment = $this->transactionService->processPaymentMethod( $deposit_payment_method, $deposit_bank_id, $store_id );

				$records = [];
				
				if ( 3 == $deposit_payment_method ) {
					if ( is_numeric( $deposit_bank_amount ) && $deposit_bank_amount > 0 ) {
						$records[] = [
							'name' => $record_name,
							'type' => 'in',
							'value' => $deposit_bank_amount,
							'note' => 'Đặt cọc hợp đồng ' . $order_id . ' thông qua chuyển khoản',
							'user_id' => Auth::id(),
							'order_id' => $order_id,
							'store_id' => $store_id,
							'bank_id'  => $deposit_bank_id,
							'cash_id'  => null,
							'payment_method' => $deposit_payment_method,
							'object_name' => $object_name,
						];
					}
					if ( is_numeric( $deposit_cash_amount ) && $deposit_cash_amount > 0 ) {
						$records[] = [
							'name' => $record_name,
							'type' => 'in',
							'value' => $deposit_cash_amount,
							'note' => 'Đặt cọc hợp đồng ' . $order_id . ' thông qua tiền mặt',
							'user_id' => Auth::id(),
							'order_id' => $order_id,
							'store_id' => $store_id,
							'bank_id'  => null,
							'cash_id'  => $get_deposit_payment['cash_id'],
							'payment_method' => $deposit_payment_method,
							'object_name' => $object_name,
						];
					}
				} else {
					$records[] = [
						'name' => $record_name,
						'type' => 'in',
						'value' => $firt_deposit_amount,
						'note' => 'Đặt cọc hợp đồng ' . $order_id,
						'user_id' => Auth::id(),
						'order_id' => $order_id,
						'store_id' => $store_id,
						'bank_id' => $get_deposit_payment['bank_id'],
						'cash_id' => $get_deposit_payment['cash_id'],
						'payment_method' => $deposit_payment_method,
						'object_name' => $object_name,
					];
				}

				if ( ! empty( $records ) ) {
					foreach ( $records as $record ) {
						if ( $is_updating_old_record ) {
							$created_at = ( $order->deposit_contract_created_at ) ? $order->deposit_contract_created_at : $order->created_at;
							if ( $created_at ) {
								$record['created_at'] = $created_at;
							}
						}

						if ( ! $is_updating_old_record && $request->get('created_at') ) {
							$record['created_at'] = DateTimeHelper::parse($request->get('created_at'));
						}

						$created = $this->transactionRepository->create( $record );
						if ( $created && $created->id ) {
							$first_deposit_transaction_ids[] = $created->id;
						}
					}
				}

				$order_update_data = [ 'first_deposit_amount' => $firt_deposit_amount ];
				$transaction_detais = [
					'payment_method' => $firt_deposit_payment_method,
				];
				if ( ! empty( $first_deposit_transaction_ids ) ) {
					$transaction_detais['row_ids'] = $first_deposit_transaction_ids;
				}
				$order_update_data['first_deposit_payment_method'] = serialize( $transaction_detais );
		
				$order->update( $order_update_data );
			}
		}
		
	}

	public function maybeCreateOrderAdditionalDeposit($request, $order, $is_updating_old_record = false) {
		$additional_deposit_amount = $request->get('additional_deposit_amount', 0 );
		$additional_deposit_payment_method = $request->get('additional_deposit_payment_method');

		$order_id = $order->id;
		$store_id = $request->get('store_id');
		$first_deposit_transaction_ids = array();

		if ( is_numeric( $additional_deposit_amount ) && $additional_deposit_amount > 0 ) {
			if ( is_array( $additional_deposit_payment_method ) && isset( $additional_deposit_payment_method['payment_method'] ) ) {
				$record_name = 'order:additional_deposit';
				$object_name = 'additional_deposit';

				$deposit_bank_amount = $additional_deposit_payment_method['bank_transfer_amount'];
				$deposit_cash_amount = $additional_deposit_payment_method['cash_amount'];
				$deposit_payment_method = $additional_deposit_payment_method['payment_method'];
				$deposit_bank_id = $additional_deposit_payment_method['bank_id'];
				$get_deposit_payment = $this->transactionService->processPaymentMethod( $deposit_payment_method, $deposit_bank_id, $store_id );

				$records = [];
				
				if ( 3 == $deposit_payment_method ) {
					if ( is_numeric( $deposit_bank_amount ) && $deposit_bank_amount > 0 ) {
						$records[] = [
							'name' => $record_name,
							'type' => 'in',
							'value' => $deposit_bank_amount,
							'note' => 'Thu thêm cọc, hợp đồng ' . $order_id . ' thông qua chuyển khoản',
							'user_id' => Auth::id(),
							'order_id' => $order_id,
							'store_id' => $store_id,
							'bank_id'  => $deposit_bank_id,
							'cash_id'  => null,
							'payment_method' => $deposit_payment_method,
							'object_name' => $object_name,
						];
					}
					if ( is_numeric( $deposit_cash_amount ) && $deposit_cash_amount > 0 ) {
						$records[] = [
							'name' => $record_name,
							'type' => 'in',
							'value' => $deposit_cash_amount,
							'note' => 'Thu thêm cọc, hợp đồng ' . $order_id . ' thông qua tiền mặt',
							'user_id' => Auth::id(),
							'order_id' => $order_id,
							'store_id' => $store_id,
							'bank_id'  => null,
							'cash_id'  => $get_deposit_payment['cash_id'],
							'payment_method' => $deposit_payment_method,
							'object_name' => $object_name,
						];
					}
				} else {
					$records[] = [
						'name' => $record_name,
						'type' => 'in',
						'value' => $additional_deposit_amount,
						'note' => 'Thu thêm cọc, hợp đồng ' . $order_id,
						'user_id' => Auth::id(),
						'order_id' => $order_id,
						'store_id' => $store_id,
						'bank_id' => $get_deposit_payment['bank_id'],
						'cash_id' => $get_deposit_payment['cash_id'],
						'payment_method' => $deposit_payment_method,
						'object_name' => $object_name,
					];
				}

				if ( ! empty( $records ) ) {
					foreach ( $records as $record ) {
						if ( $is_updating_old_record ) {
							if ( $order->created_at ) {
								$record['created_at'] =  $order->created_at->format('Y-m-d H:i:s');
							}
						}

						$created = $this->transactionRepository->create( $record );
						if ( $created && $created->id ) {
							$first_deposit_transaction_ids[] = $created->id;
						}
					}
				}

				$order_update_data = [ 'additional_deposit_amount' => $additional_deposit_amount ];
				$transaction_detais = [
					'payment_method' => $additional_deposit_payment_method,
				];
				if ( ! empty( $first_deposit_transaction_ids ) ) {
					$transaction_detais['row_ids'] = $first_deposit_transaction_ids;
				}
				$order_update_data['additional_deposit_payment_method'] = serialize( $transaction_detais );
		
				$order->update( $order_update_data );
			}
		}
		
	}

    public function createFirstDeposit( $request, $order ) {
		$without_input_deposit = $request->get('create_order_without_input_deposit', false);
		$without_input_rental_fee = $request->get('create_order_without_input_rental_fee', false);

		$update_order_data = [];
		
		if ( ! $without_input_deposit ) { // Mark this order is created without collect deposit.
			$this->maybeCreateOrderFirstDeposit( $request, $order );
		} else {
			$update_order_data['created_without_collect_deposit'] = true;
		}

		if ( ! $without_input_rental_fee ) { // Mark this order is created without collect rental fee.
			$this->maybeCreateOrderRentalFee( $request, $order );
		} else {
			$update_order_data['created_without_collect_rental_fees'] = true;
		}
		if ( ! empty( $update_order_data ) ) {
			$order->update($update_order_data);
		}
    }

    public function updateFee($request,$order ){
        $order_items = $request->get('order_items');
        foreach ($order_items as $key => $order_item) {
            foreach ($order_item['order_item_fees'] as $key2=>$fee) {
                $result = $this->transactionService->processPaymentMethod($request->get('other_fee_payment_method'), $request->get('other_fee_bank_id'),$request->store_id);
                $transaction_id = $fee['id'];
                $data = [
                    'name'=>$fee['name'],
                    'note'=>$fee['note'],
                    'value'=> $fee['value'],
                    'order_id'=>$order['id'],
                    'type' => 'in',
                    'status' => 'approved',
                    'store_id' => $request->get('store_id'),
                    'user_id' =>Auth::id(),
                    'object_id' => 0,
                    'order_item_id' => $order_item['id'],
                    'bank_id' =>    $result['bank_id'],
                    'cash_id' => $result['cash_id'],
                    'payment_method' => $request->get('other_fee_payment_method')
                ];
                if ( $transaction_id){
                    $tran = Transaction::find($transaction_id);
                 
                    if ($tran){
                         $this->transactionRepository->update($data,  $transaction_id);
                         
                    }  
                } else {
                    $this->transactionRepository->create($data );
                }
                
            }
        }
    }

    public function deposit(Order $order)
    {
        return $order->save();
    }


    public function complete(Request $request, Order $order)
    {   
        $this->updateFee( $request, $order );
		// $completed_at = Carbon::createFromFormat('d-m-Y H:i:s', $request->get('completed_at'));
		$completed_at = DateTimeHelper::parse($request->get('completed_at'));

		$payment_method = $request->get('refund_payment_method');
		$bank_transfer_amount = $request->get('bank_transfer_amount');
		$cash_amount = $request->get('cash_amount');

		/*	
        $order->orderItems->each(function ($orderItem) use ($completed_at) {
            $orderItem->completed_at = $completed_at;
            $orderItem->save(); 
        }); */

		$outdate_or_early_amount = 0;
		$items = $request->get('order_items');
		foreach ($items as $item){
			$order_vehicle_detail = OrderVehicleDetail::find($item['id']);
			$item_data = [
				'completed_at'    => $completed_at,
				'money_out_date'  => $item['money_out_date'],
				'minute_out_date' => $item['minute_out_date'],
			];

			if ( isset( $item['odometer_after'] ) && is_numeric( $item['odometer_after'] ) ) {
				$item_data['odometer_after'] = intval( $item['odometer_after'] );
			}

			$updated = $order_vehicle_detail->update($item_data);
			$outdate_or_early_amount = $outdate_or_early_amount + $item['money_out_date'];
		}
        
		// Tính tiền quá hạn và total
		/* Pause because these action are called before process complete action.
		CarRentalHelper::writeMoneyOutDateAndTotal($order);
		CarRentalHelper::whenOrderReturnEarly($order);
		*/
        
        // Chỉ Hoàn Thành mà k thanh toán thì return luôn:
        $order_paid = $request->isPaid ;
        if (!$order_paid){
            $order->update([
                'order_status' => OrderValidator::ORDER_UNPAID,
            ]);
            return;
        }

		$using_custom_refund = $request->get('editing_custom_refund');
		$total_refund_amount = $request->get('total_refund_amount');

		$update_order_row = [
            'order_status' => OrderValidator::ORDER_COMPLETED,
			'outdate_or_early_amount' => $outdate_or_early_amount,
			'total' => $order->total + $outdate_or_early_amount,
			'completed_at' => DateTimeHelper::now(),
			'default_refund_amount' => $total_refund_amount,
        ];
        
		if ( $using_custom_refund ) {
			$custom_refund_amount = $request->get('custom_refund_amount');
			$update_order_row['custom_refund_amount'] = $custom_refund_amount;
			$total_refund_amount = $custom_refund_amount;
		}
		$order->update($update_order_row);
        
        // tính tổng số tiền trả sớm (hoặc muộn) của tất cả order items:
		/*		
        $items = $order->orderItems->toArray();
        $order_money_out_date = array_reduce($items,function($accu,$item){
            $order_vehicle_detail = OrderVehicleDetail::find($item['id']);
            return $accu + $order_vehicle_detail['money_out_date'];
        },0);
		*/
       
        // Tính số tiền sẽ hoàn trả cho khách (hoặc thu thêm của khách). Phải tính lại vì nhiều trường hợp thời điểm bấm hoàn thành ko trùng thời điểm trả xe.
        // $refund =  ($order->pid - $order->total  - $order_money_out_date); 
        // $refund =  ($order->pid - $order->total); # không trừ $order_money_out_date vì CarRentalHelper::writeMoneyOutDateAndTotal($order) nó đã cộng dồn tiền $order_money_out_date vào $order->total rồi.
        
		$description = null;
		$refund = -1 * $total_refund_amount;
		$note = $refund < 0 ? 'Hoàn thành hợp đồng và thu nợ order ' : 'Hoàn thành hợp đồng và hoàn cọc order ';
        $type = $refund < 0 ? 'in' : 'out';

        $result = $this->transactionService->processPaymentMethod($request->refund_payment_method, $request->refund_bank_id, $request->store_id);

		// Input deposit: 0 && input rental_fee: 0 => complete: calc rental_fee, calc outdate_money, set deposit=0
		// Input deposit: 500k && input rental_fee: 0 => calc refund.
		// Input deposit: 0 && input rental_fee: 300K => calc refund

		if (abs($refund)) {
			if ( is_numeric( $payment_method ) && 3 == intval( $payment_method ) && is_array( $result ) ) {
				$store_id = $order->store_id;
				$order_id = $order->id;
				$results = [];

				if ( is_numeric( $bank_transfer_amount ) && $bank_transfer_amount > 0 && isset( $result['bank_id'] ) && ! empty( $result['bank_id'] ) ) {
					$bank_transfer_transaction = [
						'name' => 'order:complete:' . $order_id,
						'type' => $type,
						'value' => abs( $bank_transfer_amount ),
						'note' => $note . $order->id . ' thông qua chuyển khoản',
						'user_id' => Auth::id(),
						'order_id' => $order_id,
						'store_id' => $store_id,
						'payment_method' => $payment_method,
						'bank_id' => $result['bank_id'],
						'cash_id' => null,
						'status' => 'approved',
						'desc'  => $description,
					];
					$this->transactionRepository->create( $bank_transfer_transaction );
				}
				if ( is_numeric( $cash_amount ) && $cash_amount > 0 && isset( $result['cash_id'] ) && ! empty( $result['cash_id'] ) ) {
					$cash_transaction = [
						'name' => 'order:complete:' . $order_id,
						'type' => $type,
						'value' => abs( $cash_amount ),
						'note' => $note . $order->id . ' thông qua tiền mặt',
						'user_id' => Auth::id(),
						'order_id' => $order_id,
						'store_id' => $store_id,
						'payment_method' => $payment_method,
						'bank_id' => null,
						'cash_id' => $result['cash_id'],
						'status' => 'approved',
						'desc'  => $description,
					];
					$this->transactionRepository->create( $cash_transaction );
				}
			} else {
				$dataTransaction = [
					'name' => 'order:complete:' . $order->id,
					'type' => $type,
					'value' => abs($refund),
					'note' => $note . $order->id,
					'user_id' => Auth::id(),
					'order_id' => $order->id,
					'store_id' => $order->store_id,
					'payment_method' => $request->refund_payment_method,
					'bank_id' =>    $result['bank_id'],
					'cash_id' => $result['cash_id'],
					'status' => 'approved',
					'desc'  => $description,
				];
				$this->transactionRepository->create($dataTransaction);
			}
		}
    }

    public function updateVehicle(Order $order, $request = null)
    {

		$items = $request->get('order_items');
		if ( is_array( $items ) && ! empty( $items ) ) {
			foreach ($items as $item){
				$order_vehicle_detail = OrderVehicleDetail::find($item['id']);

				if ( $item['vehicle_id'] && isset( $item['odometer_after'] ) && ! empty( $item['odometer_after'] ) ) { // If pass the odometer_after then update odometer for the vehicle.
					Vehicle::where('id', $item['vehicle_id'])->update([ 'odometer' => $item['odometer_after'] ]);
				}
			}
		}
		
        $idVehicles = $order->orderItems()->pluck('vehicle_id')->toArray();
        return $this->vehicleRepository->whereIn('id', $idVehicles)->update(['status' => Vehicle::STATUS_READY]);
    }
 
    private function saveOrderLog(Request $request, Order $order, $is_create = false)
    {
        $log = new ActivityLog();
        $log->name = 'order:' . $order->id;
        $log->order_id = $order->id;
        $log->user_id = Auth::id();
        $log->action = 'update';
		$log->content = 'Sửa hợp đồng số ' . $order->id;
		if ($request->get('start_this_contract')) {
			$log->content = 'Kích hoạt hợp đồng số ' . $order->id . ' từ HĐ đặt cọc sang HĐ thuê xe';
		}

		if ($is_create) {
			$log->action = 'create';
			$log->content = 'Thêm mới hợp đồng số ' . $order->id;
			if ($request->get('create_order_without_input_deposit', false)) {
				$log->content = $log->content . '. Tạo hợp đồng mà chưa thu cọc';
			}
			if ($request->get('create_order_without_input_rental_fee', false)) {
				$log->content = $log->content . '. Tạo hợp đồng mà chưa thu phí thuê';
			}
		}
        
        $log->metadata = json_encode([
            'old_data' => [
                'store_id' => $order->store_id,
                'customer_name' => $order->customer->name,
                'customer_phone' => $order->customer->phone,
                'customer_address' => $order->customer->address,
                'customer_idnumber' => $order->customer->id_card,
                'vehicle_ids' => $order->vehicles()->get()->toArray(),
                'order_type' => $order->order_type,
                'order_status' => $order->status,
                'rent_at' => $order->rent_at,
                'die_time' => $order->die_time,
                'return_at' => $order->return_at,
            ]
        ]);
        $log->save();
    }


    public function calTotalWithNoOutdate($order){
        $orderItems = $order->orderItems;
        $totalOrder = 0;
        foreach ($orderItems as $orderItem) {
			if ($orderItem->vehicle) {
				if ($orderItem->handler_price) {
					$totalOrder += $orderItem->handler_price;
				} else {
					$totalOrder += $orderItem->total_money  ;
				}
			}
        }
        return $totalOrder;
    }


    /**
     * @param $order_id
     * @param $price
     * @param $user_id
     * @return mixed
     */
    public function addOnPrice($request, $user)
    {
        $result = $this->transactionService->processPaymentMethod($request->payment_method, $request->get('bank_id'), $request->store_id);
		$line_item_id = $request->get('line_item_id');
		$new_return_at = $request->get('return_at_formatted');
		$note_suffix = ' Hẹn trả vào ' . $new_return_at . '.';

		$row_data = [];
		$results = [];

		if ( is_numeric( $request->payment_method ) && 3 == intval( $request->payment_method ) && is_array( $result ) ) {
			$bank_transfer_amount = $request->get('bank_transfer_amount');
			$cash_amount = $request->get('cash_amount');
			$payment_method = $request->get('payment_method');
			$store_id = $request->get('store_id');
			$order_id = $request->get('order_id');
			$price = $request->get('price');
			
			if ( is_numeric( $bank_transfer_amount ) && $bank_transfer_amount > 0 && isset( $result['bank_id'] ) && ! empty( $result['bank_id'] ) ) {
				$bank_transfer_transaction = [
					'order_id' => $order_id,
					'payment_method' => $payment_method,
					'bank_id' => $result['bank_id'],
					'cash_id' => null,
					'store_id' => $store_id,
					'value' => $bank_transfer_amount,
					'user_id' => Auth::id(),
					'name' => 'addon',
					'type' => 'addon',
					'note' => 'Gia hạn thêm cho hợp đồng: ' . $order_id . ' thông qua chuyển khoản.' . $note_suffix,
					'status' => 'approved',
					'object_type' => 'line_item_id',
					'object_id' => $line_item_id,
				];
				$row_data[] = $bank_transfer_transaction;
			}
			if ( is_numeric( $price ) && $price > 0 && isset( $result['cash_id'] ) && ! empty( $result['cash_id'] ) ) {
				$cash_transaction = [
					'order_id' => $order_id,
					'payment_method' => $payment_method,
					'bank_id' => null,
					'cash_id' => $result['cash_id'],
					'store_id' => $store_id,
					'value' => $price,
					'user_id' => Auth::id(),
					'name' => 'addon',
					'type' => 'addon',
					'note' => 'Gia hạn thêm cho hợp đồng: ' . $order_id . ' thông qua tiền mặt.' . $note_suffix,
					'status' => 'approved',
					'object_type' => 'line_item_id',
					'object_id' => $line_item_id,
				];
				$row_data[] = $cash_transaction;
			}
		} else {
			$row_data[] = [
				'order_id' => $request->order_id,
				'payment_method' => $request->payment_method,
				'bank_id' => $result['bank_id'],
				'cash_id' => $result['cash_id'],
				'store_id' => $request->store_id,
				'value' => $request->price,
				'user_id' => $user->id,
				'name' => 'addon',
				'type' => 'addon',
				'note' => 'Gia hạn thêm cho hợp đồng: ' . $request->order_id . '.' . $note_suffix,
				'status' => 'approved',
				'object_type' => 'line_item_id',
				'object_id' => $line_item_id,
			];
		}

		if ( ! empty( $row_data ) ) {
			$created_at = DateTimeHelper::parse($request->get('created_at'));
			foreach ( $row_data as $r_data ) {
				$r_data['created_at'] = $created_at;
				$results[] = $this->transactionRepository->create( $r_data );
			}
		}
		return $results;
    }

    public function updateReturnAt($order_id, $date)
    {
        $order = $this->orderRepository->find($order_id);
        if (!$order) {
            return false;
        }

        return $order->orderItems()->update([
            'return_at' => DateTimeHelper::parse($date) 
        ]);
    }
 
  
    public function getOrderItemPrice($order_item){
        if ($order_item->handler_price){
            return  $order_item->handler_price;
        } else {
            return ($order_item->total_money + $order_item->money_out_date);
        }
    }

	public function calcOrderReturnEarlyAmount(Request $request, Order $order)
    {
		//  // Tính tiền quá hạn và total 
		// $res1 = CarRentalHelper::calcOrderOutDateAndTotal($order, $request->get('completed_at'));

		//  // nếu rơi vào trường hợp trả sớm thì update money_out_date âm
		// $res2 = CarRentalHelper::calcOrderReturnEarlyAmount($order, $request->get('completed_at'));

       	// return array(
		// 	'calcOrderOutDateAndTotal' => $res1,
		// 	'calcOrderReturnEarlyAmount' => $res2,
		// );

		return CarRentalHelper::calcOrderReturnEarlyAmount($order, $request->get('completed_at'));
	}

	public function closeDeposit(Request $request) {
		$order_id = $request->get('order_id');
		$order = Order::find( $order_id );
		$option = intval( $request->get('option') );
		$user_id = Auth::id();

		// Close order.
		$update_order_data = [
			'deposit_closed' => $option, // 1: chỉ đóng hợp đồng cọc, 2: đóng hợp đồng cọc và chuyển khoản cọc thành phí thuê.
			'order_status' => OrderValidator::ORDER_COMPLETED,
			'completed_at' => DateTimeHelper::now(),
		];
		$order->update( $update_order_data );

		// Release vehicle
		$vehicle_ids = OrderVehicleDetail::where('order_id', $order_id)->pluck('vehicle_id')->toArray();
		if ( ! empty( $vehicle_ids ) ) {
			$items = $this->vehicleRepository->whereIn('id', $vehicle_ids);
			if ( $items ) {
				$items->update(['status' => Vehicle::STATUS_READY]);
			}
		}

		if ( 2 == $option ) { // Change the first_deposit to rental_fees
			$rental_fee_created_at = $request->get('rental_fee_created_at');
			$new_trans_created_at = DateTimeHelper::parse($rental_fee_created_at);

			$deposit_transaction = Transaction::where('name', 'order:deposit:keep_vehicle')->where('order_id', $order_id)->first();
			if ( $deposit_transaction ) {
				$deposit_val = $deposit_transaction->value;
				// Update old deposit transaction value to 0.
				$deposit_transaction->update(['value' => 0, 'desc' => "Người dùng #{$user_id} thanh lý hợp đồng cọc và chuyển tiền cọc thành phí thuê xe cho ngày {$rental_fee_created_at}"]);

				// Create new transaction record for rental_fees
				$record_name = 'order:rental_fees';
				$object_name = 'rental_fees';

				$records = [
					'name' => $record_name,
					'type' => 'in',
					'value' => $deposit_val,
					'note' => 'Thanh lý và chuyển cọc thành phí thuê xe, hợp đồng ' . $order_id,
					'user_id' => $user_id,
					'order_id' => $order_id,
					'store_id' => $deposit_transaction->store_id,
					'bank_id' => $deposit_transaction->bank_id,
					'cash_id' => $deposit_transaction->cash_id,
					'payment_method' => $deposit_transaction->payment_method,
					'object_name' => $object_name,
				];
				$this->transactionRepository->create( $records );
			}
		}

		$log = new ActivityLog();
		$log->name = 'order:' . $order_id;
		$log->order_id = $order_id;
		$log->user_id = $user_id;
		$log->action = 'close';
		$log->content = 'Thanh lý hợp đồng ' . $order->id . ' với tùy chọn: ' . ( 2 == $option ) ? 'Thanh lý hợp đồng và chuyển cọc thành phí thuê xe' : 'Thanh lý hợp đồng';
		$log->save();

		return ['success' => true];
	}
}
