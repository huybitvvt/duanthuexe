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
use App\Support\RentalPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\OrderVehicleDetailRepositoryEloquent;
use App\Exceptions\CustomException;
use App\Models\Cash;
use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Http\Services\ContractNumberService;

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
        $isDraft = filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN);
        $customer = $this->storeOrUpdateCustomer($request);
        $order = $this->updateOrCreateOrder($request, null, $customer);
        $this->updateVehicles($request, $order);
        if (!$isDraft) {
            $this->createFirstDeposit($request, $order);
        }
        if (!$isDraft && $request->has('leads') && isset($request->leads)){
            $this->updateLeads($request->get('leads'),$order->id);
        }
		$this->saveOrderLog($request, $order, true);
        $this->maybeGenerateContractSnapshot($order);
        return $order;
    }

    public function update(Request $request , Order $order )
    {
        // Concurrency protection: lock the order row in database
        $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
        if (data_get($order->contract_snapshot, 'is_locked')) {
            throw ValidationException::withMessages(['contract' => 'Hợp đồng đã chốt. Không thể ghi đè thông tin đã ký; các thao tác trả xe và gia hạn vẫn dùng luồng riêng.']);
        }

        $saveAsDraft = filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN);
        $customer = $this->storeOrUpdateCustomer($request, $order);

        $this->updateOrCreateOrder($request, $order, null);

        $this->saveOrderLog($request, $order);
        
        if (!$saveAsDraft) {
            $this->updateTransactions($request, $order);
        }
        $transaction_ids_to_destroy = $request->get('transaction_ids_to_destroy');
        if (is_array($transaction_ids_to_destroy)) {
            foreach ($transaction_ids_to_destroy as $id){
                $deleted = Transaction::destroy($id);        
            }
        }
        
		$this->updateVehicles($request, $order);
        if (!$saveAsDraft) {
            $this->updateFee( $request, $order );
        }
		$this->updateOrderVehicleDetails($request, $order);

        if (!$saveAsDraft && $request->has('leads') && isset($request->leads)){
            $this->updateLeads($request->get('leads'),$order->id);
        }

        $this->maybeGenerateContractSnapshot($order);
        if ($request->get('lock_contract') || $request->get('is_locked')) {
            $order = $this->lockContract($order);
        }
        return $order;
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
		$manualNumber = trim((string) $request->get('manual_contract_number', ''));
		if (Schema::hasColumn('orders', 'draft_reference') && $request->has('draft_reference')) {
			$dataOrder['draft_reference'] = trim((string) $request->get('draft_reference')) ?: null;
		}

		// Bổ sung các trường thông tin hợp đồng từ request
		$contractFields = [
			'contract_signed_on',
			'contract_responsible_user_id',
			'contract_authorization_date',
			'contract_authorization_party_name',
			'contract_collateral_description',
			'contract_signer_a_name',
			'contract_signer_b_name',
			'customer_source',
			'customer_source_url',
			'return_signer_a_name',
			'return_signer_b_name',
			'return_additional_note',
		];

		foreach ($contractFields as $field) {
			if ($request->has($field)) {
				$val = $request->get($field);
				if (in_array($field, ['contract_signed_on', 'contract_authorization_date']) && $val) {
					$dataOrder[$field] = DateTimeHelper::parse($val);
				} else {
					$dataOrder[$field] = $val;
				}
			}
		}

		// If $order is null, create a new order; otherwise, update the existing order
		if ($order === null) {
			$created_at = DateTimeHelper::parse($request->get('created_at'));

			$dataOrder['customer_id'] = $customer->id;
			$dataOrder['order_type'] = OrderValidator::ORDER_TYPE_RENTING;
			$dataOrder['order_status'] = filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN)
				? OrderValidator::ORDER_DRAFT
				: OrderValidator::ORDER_RENTING;
			$dataOrder['data_version'] = 2;
			$dataOrder['return_adjustment_applied'] = 0;
			$dataOrder['created_at'] = $created_at ?: Carbon::now('Asia/Ho_Chi_Minh');

			if (empty($dataOrder['contract_signed_on'])) {
				$dataOrder['contract_signed_on'] = $dataOrder['created_at'] ? Carbon::parse($dataOrder['created_at'])->toDateString() : Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
			}
			if (empty($dataOrder['contract_responsible_user_id'])) {
				$dataOrder['contract_responsible_user_id'] = Auth::id();
			}
			if (empty($dataOrder['contract_signer_b_name'])) {
				$dataOrder['contract_signer_b_name'] = $customer ? $customer->name : $request->get('customer_name');
			}

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
				$dataOrder['contract_number'] = null;
			} elseif ($dataOrder['order_status'] === OrderValidator::ORDER_DRAFT) {
				$dataOrder['contract_number'] = null;
				$dataOrder['contract_issued_at'] = null;
				if ($manualNumber !== '') {
					$dataOrder['draft_reference'] = $manualNumber;
				}
			} else {
				// Sinh số HĐ dạng YYYY/MM/DD-0001
				$dataOrder['contract_number'] = $manualNumber !== ''
					? $manualNumber : ContractNumberService::generate($dataOrder['contract_signed_on']);
				$dataOrder['contract_issued_at'] = Carbon::now('Asia/Ho_Chi_Minh');
			}

			return $this->orderRepository->store($dataOrder);
		} else { // update existing order
			$additional_deposit_amount = $request->get('additional_deposit_amount');

			if (filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN)) {
				$dataOrder['order_status'] = OrderValidator::ORDER_DRAFT;
				if ($manualNumber !== '') {
					$dataOrder['draft_reference'] = $manualNumber;
				}
			} elseif ($order->order_status === OrderValidator::ORDER_DRAFT) {
				$dataOrder['order_status'] = OrderValidator::ORDER_RENTING;
				if (empty($order->contract_number) || $manualNumber !== '') {
					$signDate = $request->get('contract_signed_on') ?: ($order->contract_signed_on ?: Carbon::now('Asia/Ho_Chi_Minh'));
					$paperNumber = $manualNumber !== '' ? $manualNumber : trim((string) ($dataOrder['draft_reference'] ?? $order->draft_reference ?? ''));
					if ($paperNumber !== '' && Order::where('contract_number', $paperNumber)->where('id', '!=', $order->id)->exists()) {
						throw ValidationException::withMessages(['manual_contract_number' => 'Mã giấy/bản nháp này đã dùng cho hợp đồng khác.']);
					}
					$dataOrder['contract_number'] = $paperNumber !== '' ? $paperNumber : ContractNumberService::generate($signDate);
					$dataOrder['contract_issued_at'] = Carbon::now('Asia/Ho_Chi_Minh');
				}
			} else {
				if ($manualNumber !== '' && $manualNumber !== $order->contract_number) {
					if (Order::where('contract_number', $manualNumber)->where('id', '!=', $order->id)->exists()) {
						throw ValidationException::withMessages(['manual_contract_number' => 'Mã hợp đồng này đã được sử dụng cho hợp đồng khác.']);
					}
					$dataOrder['contract_number'] = $manualNumber;
				}
			}

			if ($request->get('order_status') == 'bad_debt'){
				$dataOrder['order_status'] = OrderValidator::ORDER_BAD_DEBT;
				$dataOrder['out_dated_at'] = 0;
				$dataOrder['total'] =  $this->calTotalWithNoOutdate($order);
			} 

			if ($request->get('editing_order_created_at')) { 
				$created_at = DateTimeHelper::parse($request->get('created_at'));
				if ($created_at) {
					$dataOrder['created_at'] = $created_at;
				}
			}

			if ($request->get('order_status') == 'deposit_contract' && $request->get('start_this_contract')) { 
				$dataOrder['created_at'] = DateTimeHelper::now();
				$dataOrder['updated_at'] = DateTimeHelper::now();
				$dataOrder['order_status'] = OrderValidator::ORDER_RENTING;

				// Kích hoạt hợp đồng cọc -> cấp Số HĐ nếu chưa có
				if (empty($order->contract_number)) {
					$signDate = $request->get('contract_signed_on') ? DateTimeHelper::parse($request->get('contract_signed_on')) : Carbon::now('Asia/Ho_Chi_Minh');
					$paperNumber = $manualNumber !== '' ? $manualNumber : trim((string) ($dataOrder['draft_reference'] ?? $order->draft_reference ?? ''));
					$dataOrder['contract_number'] = $paperNumber !== '' ? $paperNumber : ContractNumberService::generate($signDate);
					$dataOrder['contract_issued_at'] = Carbon::now('Asia/Ho_Chi_Minh');
				}
			} elseif (!filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN) && empty($order->contract_number) && !in_array($order->order_status, [OrderValidator::ORDER_DEPOSIT_CONTRACT, OrderValidator::ORDER_DRAFT])) {
				// Đơn thuê cũ chưa có số -> cấp số dựa trên ngày ký hoặc ngày tạo
				$signDate = $order->contract_signed_on ?: ($order->created_at ?: Carbon::now('Asia/Ho_Chi_Minh'));
				$dataOrder['contract_number'] = $manualNumber !== '' ? $manualNumber : ContractNumberService::generate($signDate);
				$dataOrder['contract_issued_at'] = Carbon::now('Asia/Ho_Chi_Minh');
			}
			if ($manualNumber !== '' && $order->contract_number && $order->contract_number !== $manualNumber) {
				$dataOrder['contract_number'] = $manualNumber;
			}

			$order->update($dataOrder);
			return $order;
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

	/**
	 * Return the payment settings stored inside an order's serialized payment
	 * details. Older orders may store the settings directly, so support both
	 * shapes while comparing an update.
	 */
	protected function storedPaymentMethod($value)
	{
		if ( is_string($value) ) {
			$value = @unserialize($value);
		}

		if ( ! is_array($value) ) {
			return null;
		}

		if ( isset($value['payment_method']) && is_array($value['payment_method']) ) {
			return $value['payment_method'];
		}

		return $value;
	}

	protected function paymentMethodSignature($payment_method, $total_amount = 0)
	{
		if ( ! is_array($payment_method) ) {
			return null;
		}

		$method = isset($payment_method['payment_method']) && is_numeric($payment_method['payment_method'])
			? intval($payment_method['payment_method'])
			: 1;
		$bank_amount = isset($payment_method['bank_transfer_amount']) && is_numeric($payment_method['bank_transfer_amount'])
			? max(0, intval($payment_method['bank_transfer_amount']))
			: 0;
		$cash_amount = isset($payment_method['cash_amount']) && is_numeric($payment_method['cash_amount'])
			? max(0, intval($payment_method['cash_amount']))
			: 0;
		$total_amount = is_numeric($total_amount) ? max(0, intval($total_amount)) : 0;

		// Legacy records only kept the selected method. Reconstruct the one-sided
		// amount so they compare equal to the new direct-allocation format.
		if ( 0 === $bank_amount && 0 === $cash_amount && $total_amount > 0 ) {
			if ( 2 === $method ) {
				$bank_amount = $total_amount;
			} else {
				$cash_amount = $total_amount;
			}
		}

		return [
			'payment_method' => $method,
			'bank_id' => $bank_amount > 0 && isset($payment_method['bank_id']) && is_numeric($payment_method['bank_id'])
				? intval($payment_method['bank_id'])
				: null,
			'bank_transfer_amount' => $bank_amount,
			'cash_amount' => $cash_amount,
		];
	}

	protected function paymentMethodChanged($stored, $input, $total_amount = 0)
	{
		if ( ! is_array($input) ) {
			return false;
		}

		return $this->paymentMethodSignature($stored, $total_amount) !== $this->paymentMethodSignature($input, $total_amount);
	}

	protected function maybeUpdateTransactions( Request $request, Order $order ) {
		$order_id = $order->id;
		$db_order = Order::find($order_id);
		
		if ( $db_order ) {
			$decode_first_deposit = $this->storedPaymentMethod($db_order->first_deposit_payment_method);
			$decode_rental_fee = $this->storedPaymentMethod($db_order->total_rental_payment_method);
			$decode_additional_deposit = $this->storedPaymentMethod($db_order->additional_deposit_payment_method);

			$input_first_deposit = $request->get('first_deposit_payment_method');
			$input_rental_fee = $request->get('total_rental_payment_method');
			$input_additional_deposit = $request->get('additional_deposit_payment_method');
			

			if ( is_numeric( $request->first_deposit_amount ) && $request->first_deposit_amount > 0 ) { // New deposit amount diff with the db data.
				if ( $db_order->first_deposit_amount != $request->first_deposit_amount || $this->paymentMethodChanged($decode_first_deposit, $input_first_deposit, $request->first_deposit_amount) ) {
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
				if ( $db_order->additional_deposit_amount != $request->additional_deposit_amount || $this->paymentMethodChanged($decode_additional_deposit, $input_additional_deposit, $request->additional_deposit_amount) ) {
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
				if ( $db_order->total_rental_fees != $request->total_rental_fees || $this->paymentMethodChanged($decode_rental_fee, $input_rental_fee, $request->total_rental_fees) ) {
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
		$hasPricingScheme = Schema::hasColumn('order_vehicle_details', 'pricing_scheme');
		$existingPricingSchemes = $hasPricingScheme
			? $order->orderItems()->pluck('pricing_scheme', 'vehicle_id') : collect();

		foreach ($order_items as $order_item) {
			$vehicle_id = $order_item['vehicle_id'];
			$pricingScheme = array_key_exists('pricing_scheme', $order_item)
				? $order_item['pricing_scheme'] : $existingPricingSchemes->get($vehicle_id);
			if ( isset( $order_item['vehicle'] ) ) {
				$old_vehicle = $order_item['vehicle'];
				if ( $old_vehicle && isset( $old_vehicle['id'] ) && $vehicle_id != $old_vehicle['id'] ) { // The vehicle id is changed, so need to release this vehicle.
					$need_release_vehicle_ids[] = $old_vehicle['id'];
				}
			}

			if ($request->get('order_status') == 'bad_debt'){
				Vehicle::where('id', $vehicle_id)->update(['status' => Vehicle::STATUS_BAD_DEBT]);
			} else {
				if ( $request->get('order_status') != 'completed' && $order->order_status !== OrderValidator::ORDER_DRAFT ) {
					Vehicle::where('id', $vehicle_id)->update(['status' => Vehicle::STATUS_USING]);
				}
			}

			$total_money = data_get($order_item, 'total_money', 0);
			$isFlatDaily = $pricingScheme === RentalPricing::FLAT_DAILY_SCHEME
				&& data_get($order_item, 'type', 'day') === 'day';
			if ($isFlatDaily) {
				$total_money = RentalPricing::flatDailyAmount(
					DateTimeHelper::parse($order_item['rent_at']),
					DateTimeHelper::parse($order_item['return_at'])
				);
			}
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
				'borrow_hats' => data_get($order_item, 'borrow_hats', 0),
				'type' => $order_item['type'] ?? ($isFlatDaily ? 'day' : 'total'),
				'handler_price' => $order_item['handler_price'] ?? 0,
				'substitute_unit_price' => $order_item['substitute_unit_price'],
				'hiring_fee' => $hiring_fee,
				'driver_name' => data_get($order_item, 'driver_name') ?: $request->get('customer_name'),
				'driver_license_number' => data_get($order_item, 'driver_license_number'),
				'driver_license_issued_on' => data_get($order_item, 'driver_license_issued_on') ? DateTimeHelper::parse($order_item['driver_license_issued_on']) : null,
				'borrow_raincoats' => (int) data_get($order_item, 'borrow_raincoats', 0),
			];
			if ($hasPricingScheme) {
				$item_sync_data['pricing_scheme'] = $pricingScheme === RentalPricing::FLAT_DAILY_SCHEME
					? RentalPricing::FLAT_DAILY_SCHEME : null;
			}

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

		$issuedOn = $request->get('id_card_issued_on') ?: $request->get('customer_id_card_issued_on');
		if ($issuedOn !== null || $request->has('id_card_issued_on') || $request->has('customer_id_card_issued_on')) {
			$dataCustomer['id_card_issued_on'] = $issuedOn ? DateTimeHelper::parse($issuedOn) : null;
		}
		$issuedBy = $request->get('id_card_issued_by') ?: $request->get('customer_id_card_issued_by');
		if ($issuedBy !== null || $request->has('id_card_issued_by') || $request->has('customer_id_card_issued_by')) {
			$dataCustomer['id_card_issued_by'] = $issuedBy;
		}
		if ($request->has('relatives') || $request->has('customer_relatives')) {
			$relatives = $request->get('relatives') ?: $request->get('customer_relatives');
			$dataCustomer['relatives'] = is_array($relatives) ? $relatives : (is_string($relatives) ? json_decode($relatives, true) : []);
		}
	
		if ($order) {
			$order->customer()->update($dataCustomer);
			return $order->customer;
		}
	
		$customer = $request->filled('customer_id_card')
			? Customer::query()->where('id_card', $request->get('customer_id_card'))->first()
			: null;
		if (!$customer) {
			$customer = $this->customerRepository->skipPresenter()->create($dataCustomer);
		} else {
			$customer->update($dataCustomer);
		}
	
		return $customer;
	}

	public function maybeGenerateContractSnapshot(Order $order, bool $force = false)
	{
		// Cố định hợp đồng: Không ghi đè snapshot đã hoàn thành/chốt (ORDER_COMPLETED, ORDER_UNPAID) hoặc đã khóa
		$isClosed = in_array($order->order_status, [OrderValidator::ORDER_COMPLETED, OrderValidator::ORDER_UNPAID]);
		$isLocked = !empty($order->contract_snapshot['is_locked']) || $isClosed;
		if (!$force && $isLocked && !empty($order->contract_snapshot)) {
			return $order->contract_snapshot;
		}

		// Không tạo snapshot cho đơn cọc thuần túy chưa lấy xe
		if (in_array($order->order_status, [OrderValidator::ORDER_DEPOSIT_CONTRACT, OrderValidator::ORDER_DRAFT])) {
			return null;
		}

		$order->refresh();
		$order->load(['customer', 'store', 'orderItems.vehicle', 'responsibleUser', 'transactions']);

		// Thông tin mặc định Bên A (Đơn vị cho thuê)
		$companyName = config('contract.company_name', 'CÔNG TY CP THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM');
		$taxCode = config('contract.tax_code', '0110863055');
		$headOffice = config('contract.head_office', 'Sn 31 dãy C1 Tổ 28 Khu tập thể Đồng Bát, Bệnh viện 198 Bộ Công An, P. Từ Liêm, Tp. Hà Nội, VN');
		$repName = $order->contract_signer_a_name
			?: ($order->responsibleUser ? $order->responsibleUser->name : (Auth::user() ? Auth::user()->name : ''));
		$repTitle = 'Nhân viên quầy giao dịch';

		$store = $order->store;
		$customer = $order->customer;

		$existingSnapshot = is_array($order->contract_snapshot) ? $order->contract_snapshot : [];
		$contractNumber = $order->contract_number ?: ($existingSnapshot['contract_number'] ?? null);

		$issuedAt = !empty($existingSnapshot['issued_at'])
			? $existingSnapshot['issued_at']
			: ($order->contract_issued_at ? Carbon::parse($order->contract_issued_at)->format('Y-m-d H:i:s') : null);

		$itemsSnapshot = [];
		$totalHats = 0;
		$totalRaincoats = 0;

		foreach ($order->orderItems as $item) {
			$vehicle = $item->vehicle;
			$totalHats += (int) $item->borrow_hats;
			$totalRaincoats += (int) $item->borrow_raincoats;

			$isPackage = ($item->type === 'total' || !empty($item->is_all_in_one) || (float) $item->handler_price > 0);
			$pricingMode = $isPackage ? 'package' : 'day';
			$pricingUnit = $isPackage ? 'gói' : 'ngày';

			$unitPrice = 0;
			if ($isPackage) {
				if ($item->handler_price > 0) {
					$unitPrice = (float) $item->handler_price;
				} elseif ($item->substitute_unit_price > 0) {
					$unitPrice = (float) $item->substitute_unit_price;
				} else {
					$unitPrice = (float) ($item->hiring_fee ?: $item->total_money);
				}
			} else {
				if ($item->pricing_scheme === RentalPricing::FLAT_DAILY_SCHEME) {
					$unitPrice = RentalPricing::FLAT_DAILY_RATE;
				} elseif ($item->substitute_unit_price > 0) {
					$unitPrice = (float) $item->substitute_unit_price;
				} else {
					$catalogPrice = 0;
					try {
						$catalogPrice = (float) CarRentalHelper::getUnitPrice($item);
					} catch (\Throwable $e) {
						$catalogPrice = 0;
					}

					if ($catalogPrice > 0) {
						$unitPrice = $catalogPrice;
					} elseif ($item->rent_at && $item->return_at) {
						$rentAt = Carbon::parse($item->rent_at);
						$returnAt = Carbon::parse($item->return_at);
						$diffHours = $rentAt->diffInHours($returnAt);
						$days = max(1, round($diffHours / 24));
						$totalMoney = (float) ($item->hiring_fee ?: $item->total_money);
						$unitPrice = $days > 0 ? round($totalMoney / $days, 2) : $totalMoney;
					} else {
						$unitPrice = (float) ($item->hiring_fee ?: $item->total_money);
					}
				}
			}

			$itemsSnapshot[] = [
				'order_item_id' => $item->id,
				'vehicle_id' => $item->vehicle_id,
				'vehicle_name' => $vehicle ? $vehicle->name : '',
				'license' => $vehicle ? $vehicle->license : '',
				'brand' => $vehicle ? $vehicle->brand : '',
				'type' => $vehicle ? $vehicle->type : '',
				'color' => $vehicle ? $vehicle->color : '',
				'year' => $vehicle ? $vehicle->year : '',
				'driver_name' => $item->driver_name ?: ($customer ? $customer->name : ''),
				'driver_license_number' => $item->driver_license_number,
				'driver_license_issued_on' => $item->driver_license_issued_on ? Carbon::parse($item->driver_license_issued_on)->format('Y-m-d') : null,
				'borrow_hats' => (int) $item->borrow_hats,
				'borrow_raincoats' => (int) $item->borrow_raincoats,
				'rent_at' => $item->rent_at ? Carbon::parse($item->rent_at)->format('Y-m-d H:i:s') : '',
				'return_at' => $item->return_at ? Carbon::parse($item->return_at)->format('Y-m-d H:i:s') : '',
				'pricing_mode' => $pricingMode,
				'pricing_unit' => $pricingUnit,
				'unit_price' => $unitPrice,
				'handler_price' => (float) $item->handler_price,
				'substitute_unit_price' => (float) $item->substitute_unit_price,
				'hiring_fee' => (float) ($item->hiring_fee ?: $item->total_money),
				'total_money' => (float) $item->total_money,
			];
		}

		$returnConfirmation = !empty($existingSnapshot['return_confirmation']) ? $existingSnapshot['return_confirmation'] : [
			'signer_a_name' => $order->return_signer_a_name,
			'signer_b_name' => $order->return_signer_b_name,
			'additional_note' => $order->return_additional_note,
			'completed_at' => $order->completed_at ? Carbon::parse($order->completed_at)->format('d/m/Y H:i:s') : null,
		];
		if ($order->return_signer_a_name !== null) {
			$returnConfirmation['signer_a_name'] = $order->return_signer_a_name;
		}
		if ($order->return_signer_b_name !== null) {
			$returnConfirmation['signer_b_name'] = $order->return_signer_b_name;
		}
		if ($order->return_additional_note !== null) {
			$returnConfirmation['additional_note'] = $order->return_additional_note;
		}
		if ($order->completed_at) {
			$returnConfirmation['completed_at'] = Carbon::parse($order->completed_at)->format('d/m/Y H:i:s');
		}

		$snapshot = [
			'is_locked' => !empty($existingSnapshot['is_locked']) || $isClosed,
			'contract_number' => $contractNumber,
			'issued_at' => $issuedAt,
			'signed_on' => $order->contract_signed_on ? Carbon::parse($order->contract_signed_on)->format('Y-m-d') : Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
			'responsible_user' => [
				'id' => $order->contract_responsible_user_id,
				'name' => $order->responsibleUser ? $order->responsibleUser->name : (Auth::user() ? Auth::user()->name : ''),
			],
			'customer_source' => [
				'name' => $order->customer_source,
				'url' => $order->customer_source_url,
			],
			'authorization' => [
				'date' => $order->contract_authorization_date ? Carbon::parse($order->contract_authorization_date)->format('Y-m-d') : null,
				'party_name' => $order->contract_authorization_party_name,
			],
			'lessor' => [
				'company_name' => $companyName,
				'tax_code' => $taxCode,
				'head_office_address' => $headOffice,
				'representative_name' => mb_strtoupper($repName, 'UTF-8'),
				'representative_title' => $repTitle,
				'branch_name' => $store ? $store->store_name : '',
				'branch_address' => $store ? $store->store_address : '',
				'contact_phone' => $store ? $store->store_phone : '',
			],
			'customer' => [
				'name' => $customer ? $customer->name : '',
				'phone' => $customer ? $customer->phone : '',
				'address' => $customer ? $customer->address : '',
				'id_card' => $customer ? $customer->id_card : '',
				'id_card_issued_on' => $customer && $customer->id_card_issued_on ? Carbon::parse($customer->id_card_issued_on)->format('Y-m-d') : null,
				'id_card_issued_by' => $customer ? $customer->id_card_issued_by : '',
				'relatives' => $customer && is_array($customer->relatives) ? $customer->relatives : [],
			],
			'vehicles' => $itemsSnapshot,
			'accessories' => [
				'total_hats' => $totalHats,
				'total_raincoats' => $totalRaincoats,
			],
			'payment' => [
				'rental_fees' => (float) ($order->total_rental_fees ?: $order->total),
				'deposit_amount' => (float) $order->first_deposit_amount,
				'additional_deposit_amount' => (float) $order->additional_deposit_amount,
				'total_deposit' => (float) ($order->first_deposit_amount + $order->additional_deposit_amount),
				'collateral_description' => $order->contract_collateral_description,
				'deposit_payment_method' => $this->decodePaymentSettings($order->first_deposit_payment_method),
				'rental_payment_method' => $this->decodePaymentSettings($order->total_rental_payment_method),
				'transactions_summary' => $order->transactions ? $order->transactions->map(function ($t) {
					return [
						'id' => $t->id,
						'name' => $t->name,
						'type' => $t->type,
						'value' => (float) $t->value,
						'payment_method' => $t->payment_method,
						'created_at' => $t->created_at ? Carbon::parse($t->created_at)->format('Y-m-d H:i:s') : '',
					];
				})->toArray() : [],
			],
			'signers' => [
				'signer_a_name' => $order->contract_signer_a_name ?: $repName,
				'signer_b_name' => $order->contract_signer_b_name ?: ($customer ? $customer->name : ''),
			],
			'return_confirmation' => $returnConfirmation,
		];

		$order->contract_snapshot = $snapshot;
		$order->save();
		return $snapshot;
	}

	private function decodePaymentSettings($value)
	{
		if (is_array($value)) {
			return $value;
		}
		if (!is_string($value) || $value === '') {
			return $value;
		}
		$decoded = @unserialize($value);
		return $decoded !== false ? $decoded : $value;
	}

	public function updateReturnConfirmationInSnapshot(Order $order)
	{
		$order->refresh();
		if (empty($order->contract_snapshot)) {
			$this->maybeGenerateContractSnapshot($order);
			return;
		}

		$snapshot = $order->contract_snapshot;
		$snapshot['return_confirmation'] = [
			'signer_a_name' => $order->return_signer_a_name,
			'signer_b_name' => $order->return_signer_b_name,
			'additional_note' => $order->return_additional_note,
			'completed_at' => $order->completed_at ? Carbon::parse($order->completed_at)->format('d/m/Y H:i:s') : Carbon::now('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s'),
		];
		$snapshot['is_locked'] = true;
		$order->contract_snapshot = $snapshot;
		$order->save();
	}

	public function lockContract(Order $order)
	{
		return DB::transaction(function () use ($order) {
			$order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
			if (empty($order->contract_number)) {
				$signDate = $order->contract_signed_on ?: Carbon::now('Asia/Ho_Chi_Minh');
				$order->contract_number = ContractNumberService::generateForOrder($order, $signDate);
				$order->contract_issued_at = Carbon::now('Asia/Ho_Chi_Minh');
				$order->save();
			}
			$snapshot = $order->contract_snapshot;
			if (empty($snapshot)) {
				$snapshot = $this->maybeGenerateContractSnapshot($order);
			}
			if (is_array($snapshot)) {
				$snapshot['is_locked'] = true;
				$snapshot['contract_number'] = $order->contract_number;
				if (empty($snapshot['issued_at'])) {
					$snapshot['issued_at'] = $order->contract_issued_at ? Carbon::parse($order->contract_issued_at)->format('Y-m-d H:i:s') : Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
				}
				$order->contract_snapshot = $snapshot;
				if (empty($snapshot['document'])) {
					$snapshot['document'] = ContractDocumentBuilder::buildFromOrder($order);
					$order->contract_snapshot = $snapshot;
				}
				$order->save();
			}
			return $order;
		});
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
        if (!is_array($order_items)) {
            return;
        }
        foreach ($order_items as $key => $order_item) {
            if (empty($order_item['order_item_fees']) || !is_array($order_item['order_item_fees'])) {
                continue;
            }
            foreach ($order_item['order_item_fees'] as $key2=>$fee) {
                $result = $this->transactionService->processPaymentMethod($request->get('other_fee_payment_method'), $request->get('other_fee_bank_id'),$request->store_id);
                $transaction_id = $fee['id'] ?? null;
                $data = [
                    'name'=>$fee['name'] ?? '',
                    'note'=>$fee['note'] ?? '',
                    'value'=> $fee['value'] ?? 0,
                    'order_id'=>$order['id'],
                    'type' => 'in',
                    'status' => 'approved',
                    'store_id' => $request->get('store_id'),
                    'user_id' =>Auth::id(),
                    'object_id' => 0,
                    'order_item_id' => $order_item['id'] ?? null,
                    'bank_id' =>    $result['bank_id'] ?? null,
                    'cash_id' => $result['cash_id'] ?? null,
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
        $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
        if ($order->order_status === OrderValidator::ORDER_COMPLETED) return;
        $this->updateFee( $request, $order );

        $completed_at_raw = $request->get('completed_at');
        $completed_at = null;
        if (!empty($completed_at_raw)) {
            try {
                $completed_at = DateTimeHelper::parse($completed_at_raw);
            } catch (\Throwable $e) {
                $completed_at = null;
            }
        }
        $actual_return_time = $completed_at ?: DateTimeHelper::now();

		$payment_method = $request->get('refund_payment_method');
		$bank_transfer_amount = $request->get('bank_transfer_amount');
		$cash_amount = $request->get('cash_amount');

		$outdate_or_early_amount = 0;
		$items = $request->get('order_items');
		if (is_array($items)) {
			foreach ($items as $item){
				$order_vehicle_detail = OrderVehicleDetail::find($item['id']);
				if ($order_vehicle_detail) {
					$item_data = [
						'completed_at'    => $actual_return_time,
						'money_out_date'  => $item['money_out_date'] ?? 0,
						'minute_out_date' => $item['minute_out_date'] ?? 0,
					];

					if ( isset( $item['odometer_after'] ) && is_numeric( $item['odometer_after'] ) ) {
						$item_data['odometer_after'] = intval( $item['odometer_after'] );
					}

					$order_vehicle_detail->update($item_data);
				}
				$outdate_or_early_amount = $outdate_or_early_amount + ($item['money_out_date'] ?? 0);
			}
		}
        
		$using_custom_refund = $request->get('editing_custom_refund');
		$total_refund_amount = $request->get('total_refund_amount');
		$total_refund_amount = is_numeric($total_refund_amount) ? (float) $total_refund_amount : 0.0;
		$order_paid = $request->has('isPaid') ? filter_var($request->get('isPaid'), FILTER_VALIDATE_BOOLEAN) : true;

		$previous_outdate_or_early = (float) ($order->return_adjustment_applied ?? 0);
		$adjustment_delta = (float) $outdate_or_early_amount - $previous_outdate_or_early;
		$new_total = (float) $order->total + $adjustment_delta;

		$update_order_row = [
			'order_status' => $order_paid ? OrderValidator::ORDER_COMPLETED : OrderValidator::ORDER_UNPAID,
			'outdate_or_early_amount' => $outdate_or_early_amount,
			'total' => $new_total,
            'return_adjustment_applied' => $outdate_or_early_amount,
			'completed_at' => $actual_return_time,
			'default_refund_amount' => $total_refund_amount,
		];
        
		if ( $using_custom_refund ) {
			$custom_refund_amount = $request->get('custom_refund_amount');
			$custom_refund_amount = is_numeric($custom_refund_amount) ? (float) $custom_refund_amount : 0.0;
			$update_order_row['custom_refund_amount'] = $custom_refund_amount;
			$total_refund_amount = $custom_refund_amount;
		}
		if ($request->has('return_signer_a_name')) {
			$update_order_row['return_signer_a_name'] = $request->get('return_signer_a_name');
		}
		if ($request->has('return_signer_b_name')) {
			$update_order_row['return_signer_b_name'] = $request->get('return_signer_b_name');
		}
		if ($request->has('return_additional_note')) {
			$update_order_row['return_additional_note'] = $request->get('return_additional_note');
		}
		$order->update($update_order_row);
		$this->updateReturnConfirmationInSnapshot($order->fresh());

		if ( ! $order_paid ) {
			return;
		}
        
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
