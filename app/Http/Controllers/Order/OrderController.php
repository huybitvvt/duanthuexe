<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Http\Services\OrderService;
use App\Http\Services\HimotoLegalDocumentService;
use App\Http\Services\VehicleTransferService;
use App\Models\Order;
use App\Models\Bank;
use App\Models\Vehicle;
use App\Models\OrderVehicleDetail;
use App\Validators\OrderValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;
use App\Support\HimotoStores;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $orderService;
    protected $vehicleTransferService;

    public function __construct(OrderService $orderService, VehicleTransferService $vehicleTransferService = null)
    {
        $this->orderService = $orderService;
        $this->vehicleTransferService = $vehicleTransferService;
    }

    public function getOrderCarRental(Request $request): JsonResponse
    {
        $result = Order::with(['store'])->paginate(15);
        $result->map(function ($value) {
            $vehicle_ids = json_decode($value->vehicle_ids, true);
            $vehicles = Vehicle::whereIn('id', $vehicle_ids)->get();
            data_set($value, 'vehicles', $vehicles);
            return $value;
        });
        return $this->successResponse($result);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->index($request);
        return $this->successResponse(OrderListResource::collection($orders));
    }

    /**
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function show(Request $request, Order $order): JsonResponse
    {
		$order_detail = $order->load(
			[
				'addOnOrders.user:id,name', 'customer', 'vehicles', 'store', 'orderItems.orderItemFees',  'orderItems.vehicle', 'responsibleUser:id,name',
				'leads' => function ($query) {
					$query->with(['user:id,name']);
				},
				'transactions' => function ($query) {
					$query->with(['user:id,name', 'bank'])->orderBy('id','desc');
				},
				'activityLogs'=>function($query){
					$query->with('user:id,name')->orderBy('id','desc');
				}
			]
		);

		$default_payment_method = [
			'payment_method' => 1,
			'bank_id' => null,
			'bank_transfer_amount' => 0,
			'cash_amount' => 0,
		];

		$decode_first_deposit = unserialize( $order_detail->first_deposit_payment_method );
		$decode_rental_fee = unserialize( $order_detail->total_rental_payment_method );
		$decode_additional_deposit = unserialize( $order_detail->additional_deposit_payment_method );

		$order_detail->first_deposit_payment_method = ( is_array( $decode_first_deposit ) && isset( $decode_first_deposit['payment_method'] ) && ! empty( $decode_first_deposit['payment_method'] ) ) ? $decode_first_deposit['payment_method'] : $default_payment_method;
		$order_detail->total_rental_payment_method = ( is_array( $decode_rental_fee ) && isset( $decode_rental_fee['payment_method'] ) && ! empty( $decode_rental_fee['payment_method'] ) ) ? $decode_rental_fee['payment_method'] : $default_payment_method;
		$order_detail->additional_deposit_payment_method = ( is_array( $decode_additional_deposit ) && isset( $decode_additional_deposit['payment_method'] ) && ! empty( $decode_additional_deposit['payment_method'] ) ) ? $decode_additional_deposit['payment_method'] : $default_payment_method;

		if ( ! $order_detail->first_deposit_amount ) {
			$order_detail->first_deposit_amount = 0;
		}
		if ( ! $order_detail->additional_deposit_amount ) {
			$order_detail->additional_deposit_amount = 0;
		}
		if ( ! $order_detail->total_rental_fees ) {
			$order_detail->total_rental_fees = 0;
		}
        $vehicleTransferService = $this->vehicleTransferService ?: app(VehicleTransferService::class);
        $order_detail->setAttribute(
            'vehicle_exchange_history',
            $vehicleTransferService->getOrderVehicleExchangeHistory($order_detail)
        );

        return $this->successResponse($order_detail);
    }

    public function handover(Order $order, HimotoLegalDocumentService $documents)
    {
        \App\Support\PilotAccess::store(auth()->user(), $order->store_id);
        return response($documents->rentalHandover($order), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
		// return $this->successResponse('', 'Fake data');
		$this->normalizeOrderPaymentMethods($request);

		$request->validate(OrderValidator::store($request));

		if ( $error = $this->validate_input_payment( 'Tổng số tiền đặt cọc không khớp', $request->get('first_deposit_payment_method'), $request->get('first_deposit_amount'), $request->get('store_id') ) ) {
			return $error;
		}
		if ( $error = $this->validate_input_payment( 'Tổng số tiền phí thuê xe không khớp', $request->get('total_rental_payment_method'), $request->get('total_rental_fees'), $request->get('store_id') ) ) {
			return $error;
		}
		if ( $error = $this->validate_input_payment( 'Tổng số tiền cọc thu thêm không khớp', $request->get('additional_deposit_payment_method'), $request->get('additional_deposit_amount'), $request->get('store_id') ) ) {
			return $error;
		}

		$payment_method = $request->get('payment_method');
		if ( $payment_method ) {
			$bank_id = $request->get('bank_id');
			if ( in_array( intval( $payment_method ), array( 2, 3 ) ) ) {
				if ( ! $bank_id ) {
					return $this->errorResponse('Vui lòng chọn tài khoản ngân hàng', 422);
				}
			}
		}

		$order_items = $request->get('order_items');
		if( is_array( $order_items ) && ! empty( $order_items ) ) {
			foreach ( $order_items as $order_item ) {
				$rent_at = DateTimeHelper::parse( $order_item['rent_at'] );
				$return_at = DateTimeHelper::parse( $order_item['return_at'] );

				if ( $rent_at->gt( $return_at ) ) {
					return $this->errorResponse('Thời gian trả xe phải muộn hơn thời gian thuê xe', 422);
				}
			}
		}
		if (!HimotoStores::query()->whereKey((int) $request->get('store_id'))->exists()) {
			return $this->errorResponse('Kho/cơ sở không thuộc danh mục 6 kho HIMOTO.', 422);
		}
		if ($error = $this->validateOrderVehicleLocations($request)) {
			return $error;
		}

        try {
            DB::beginTransaction();
            $order = $this->orderService->store($request);
            DB::commit();
            return $this->successResponse($order, 'Thêm mới thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function update(Request $request, Order $order): JsonResponse
    {
		if (filter_var($request->get('save_as_draft', false), FILTER_VALIDATE_BOOLEAN) && $order->order_status !== OrderValidator::ORDER_DRAFT) {
			return $this->errorResponse('Không thể chuyển hợp đồng đã phát hành về bản nháp.', 422);
		}
		$this->normalizeOrderPaymentMethods($request);

		if ( $error = $this->validate_input_payment( 'Tổng số tiền đặt cọc không khớp', $request->get('first_deposit_payment_method'), $request->get('first_deposit_amount'), $request->get('store_id') ) ) {
			return $error;
		}
		if ( $error = $this->validate_input_payment( 'Tổng số tiền phí thuê xe không khớp', $request->get('total_rental_payment_method'), $request->get('total_rental_fees'), $request->get('store_id') ) ) {
			return $error;
		}
		if ( $error = $this->validate_input_payment( 'Tổng số tiền cọc thu thêm không khớp', $request->get('additional_deposit_payment_method'), $request->get('additional_deposit_amount'), $request->get('store_id') ) ) {
			return $error;
		}

		if ( 'completed' == $order->order_status && $order->deposit_closed ) {
			return $this->errorResponse('Không thể cập nhật trên hợp đồng đã thanh lý', 422);
		}

		$request->validate(OrderValidator::update($request, $order));
		if (!HimotoStores::query()->whereKey((int) $request->get('store_id'))->exists()) {
			return $this->errorResponse('Kho/cơ sở không thuộc danh mục 6 kho HIMOTO.', 422);
		}
		if ($error = $this->validateOrderVehicleLocations($request, $order)) {
			return $error;
		}
        try {
            DB::beginTransaction();
            $this->orderService->update($request, $order);
            DB::commit();
            return $this->successResponse('', 'Cập nhật thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
	}

	private function validateOrderVehicleLocations(Request $request, ?Order $order = null): ?JsonResponse
	{
		$storeId = (int) $request->get('store_id');
		foreach ((array) $request->get('order_items', []) as $item) {
			$vehicleId = (int) ($item['vehicle_id'] ?? 0);
			$vehicle = $vehicleId ? Vehicle::find($vehicleId) : null;
			if (!$vehicle) {
				return $this->errorResponse('Vui lòng chọn xe hợp lệ cho hợp đồng.', 422);
			}
			$alreadyAssigned = $order && (int) $order->store_id === $storeId
				&& $order->orderItems()->where('vehicle_id', $vehicleId)->exists();
			if (!$alreadyAssigned && ((int) ($vehicle->current_store_id ?: $vehicle->store_id) !== $storeId
				|| $vehicle->status !== Vehicle::STATUS_READY)) {
				return $this->errorResponse('Xe phải ở trạng thái sẵn sàng tại cơ sở đã chọn.', 422);
			}
		}
		return null;
	}

	protected function normalizeOrderPaymentMethods( Request $request ) {
		$payment_fields = [
			'first_deposit_payment_method' => 'first_deposit_amount',
			'total_rental_payment_method' => 'total_rental_fees',
			'additional_deposit_payment_method' => 'additional_deposit_amount',
		];

		foreach ( $payment_fields as $payment_field => $total_field ) {
			$payment_method = $request->get($payment_field);
			if ( ! is_array($payment_method) ) {
				continue;
			}

			$total_amount = is_numeric($request->get($total_field)) ? max(0, intval($request->get($total_field))) : 0;
			$bank_transfer_amount = isset($payment_method['bank_transfer_amount']) && is_numeric($payment_method['bank_transfer_amount'])
				? max(0, intval($payment_method['bank_transfer_amount']))
				: 0;
			$cash_amount = isset($payment_method['cash_amount']) && is_numeric($payment_method['cash_amount'])
				? max(0, intval($payment_method['cash_amount']))
				: 0;
			$is_other_bank = isset($payment_method['bank_id']) && $payment_method['bank_id'] === 'other';

			// Keep old clients compatible: a single selected method did not send its amount.
			if ( $total_amount > 0 && 0 === $bank_transfer_amount && 0 === $cash_amount ) {
				if ( isset($payment_method['payment_method']) && 2 === intval($payment_method['payment_method']) ) {
					$bank_transfer_amount = $total_amount;
				} else {
					$cash_amount = $total_amount;
				}
			}

			if ( $bank_transfer_amount > 0 && $cash_amount > 0 ) {
				$payment_method['payment_method'] = 3;
			} elseif ( $bank_transfer_amount > 0 ) {
				$payment_method['payment_method'] = 2;
			} else {
				$payment_method['payment_method'] = 1;
			}

			$payment_method['bank_transfer_amount'] = $bank_transfer_amount;
			$payment_method['cash_amount'] = $cash_amount;
			$payment_method['unregistered_bank'] = $bank_transfer_amount > 0 && $is_other_bank;
			$payment_method['bank_id'] = $bank_transfer_amount > 0 && !$is_other_bank
				? (isset($payment_method['bank_id']) ? $payment_method['bank_id'] : null)
				: null;

			$request->merge([$payment_field => $payment_method]);
		}
	}

	public function validate_input_payment( $label, $payment_method, $total_amount = 0, $storeId = null ) {
		if ( ! is_array($payment_method) || ! is_numeric($total_amount) || intval($total_amount) <= 0 ) {
			return null;
		}

		$bank_transfer_amount = isset($payment_method['bank_transfer_amount']) && is_numeric($payment_method['bank_transfer_amount'])
			? max(0, intval($payment_method['bank_transfer_amount']))
			: 0;
		$cash_amount = isset($payment_method['cash_amount']) && is_numeric($payment_method['cash_amount'])
			? max(0, intval($payment_method['cash_amount']))
			: 0;

		if ( intval($total_amount) !== $bank_transfer_amount + $cash_amount ) {
			return $this->errorResponse($label, 422);
		}

		$isOtherBank = !empty($payment_method['unregistered_bank']);
		$otherBankNote = trim((string)($payment_method['other_method_note'] ?? ''));
		if ( $bank_transfer_amount > 0 && $isOtherBank && $otherBankNote === '' ) {
			return $this->errorResponse('Vui lòng nhập tên ngân hàng hoặc hình thức chuyển khoản khác.', 422);
		}
		if ( $bank_transfer_amount > 0 && empty($payment_method['bank_id']) && !$isOtherBank ) {
			return $this->errorResponse('Vui lòng chọn tài khoản ngân hàng', 422);
		}
		if ($bank_transfer_amount > 0 && !$isOtherBank && $storeId !== null
			&& !Bank::whereKey($payment_method['bank_id'])->where('store_id', (int) $storeId)->exists()) {
			return $this->errorResponse('Tài khoản ngân hàng không thuộc cơ sở của hợp đồng.', 422);
		}

		return null;
	}

    /**
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function deposit(Request $request, Order $order): JsonResponse
    {
        $request->validate(OrderValidator::deposit());
        try {
            DB::beginTransaction();
            $this->orderService->deposit($order);
            DB::commit();
            return $this->successResponse('', 'Thêm mới thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function complete(Request $request, Order $order): JsonResponse
    {
        $request->validate(OrderValidator::complete());
        try {
			$order_paid = $request->has('isPaid') ? filter_var($request->get('isPaid'), FILTER_VALIDATE_BOOLEAN) : true;
			$using_custom_refund = $request->get('editing_custom_refund');
			$total_refund_amount = $request->get('total_refund_amount');
			if ( $using_custom_refund ) {
				$total_refund_amount = $request->get('custom_refund_amount');
			}
			
			$payment_method = $request->get('refund_payment_method');
			$bank_transfer_amount = $request->get('bank_transfer_amount');
			$cash_amount = $request->get('cash_amount');
			$bank_id = $request->get('refund_bank_id');

			if ( $order_paid && is_numeric( $payment_method ) ) {
				if ( in_array( $payment_method, array( 2, 3 ) ) && ! $bank_id ) {
					return $this->errorResponse('Vui lòng chọn một tài khoản ngân hàng', 422);
				}

				if ( 3 == intval( $payment_method ) && abs($total_refund_amount) != intval( $bank_transfer_amount ) + intval( $cash_amount ) ) {
					return $this->errorResponse('Tổng số tiền mặt và chuyển khoản bạn nhập vào không khớp với tổng tiền cần thanh toán', 422);
				}
			}

            DB::beginTransaction();
            $this->orderService->complete($request, $order);
            $this->orderService->updateVehicle($order, $request); // Update vehicle status to Ready.
            DB::commit();
            return $this->successResponse('', 'Cập nhật thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Order $order
     * @return JsonResponse
     */
    public function destroy(  $data): JsonResponse
    {
        try {
            DB::beginTransaction();
             
            if (strpos($data, ',') !== false) {
                $arr = explode(',', $data);
                $orders = Order::whereIn('id', $arr)->get();
                $orders->each(function ($order) {
                    $this->deleteOrderAndItsRelation($order);
                });
            } else {
                $order = Order::find($data);
                $this->deleteOrderAndItsRelation($order);
              
            }
    
            DB::commit();
            return $this->successResponse('', 'Xóa thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function deleteOrderAndItsRelation($order){
            $order->addOnOrders()->delete();
            
            $orderItems = $order->orderItems();
            $vehicleIds = $orderItems->pluck('vehicle_id')->toArray();
            Vehicle::whereIn('id', $vehicleIds)->update([
                'status' => Vehicle::STATUS_READY
            ]);
           
            $order->orderItems()->delete();
            $order->transactions()->delete();

            
            $order->delete();
    }
    public function addOnPrice(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required|integer',
            'price' => 'required|integer|min:1',
            'return_at' => 'required|date',
        ], [
            'price.min' => 'Tiền không được để trống',
            'return_at.required' => 'Ngày trả không được để trống'
        ]);
        
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Bạn cần đăng nhập lại', 404);
        }
        $addOn = $this->orderService->addOnPrice($request, $user);
      
		// Update the return_at of the line item.
		$line_item_id = $request->get('line_item_id');
		$return_at_formatted = DateTimeHelper::parse($request->get('return_at_formatted'));
		if ( ! empty( $return_at_formatted ) ) {
			// @mock: 01
			$line_item = OrderVehicleDetail::find($line_item_id);
			$total_renewal_amount = ( $line_item->total_renewal_amount && is_numeric( $line_item->total_renewal_amount ) && $line_item->total_renewal_amount > 0 ) ? $line_item->total_renewal_amount : 0;

			$total_renewal_amount = $total_renewal_amount + $request->get('bank_transfer_amount', 0) + $request->get('price', 0);

			OrderVehicleDetail::where('id', $line_item_id)->update([
				'return_at' => $return_at_formatted,
				'total_renewal_amount' => $total_renewal_amount,
			]);
		}

        $order = Order::find( $request->get('order_id') ) ; // Disable vì đã reset về 0 ở @mock: 01
        // $this->orderService->changeHandlerPriceByAddOnPrice($order,$request->get('price'));
		CarRentalHelper::writeMoneyOutDateAndTotal($order); // Disable vì đã reset về 0 ở @mock: 01

        if (!$addOn) {
            return $this->errorResponse('Có lỗi xảy ra', 404);
        }
        return $this->successResponse($addOn, 'Nạp tiền gia hạn thành công');
    }
  
	/**
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function calc_return_early_amount(Request $request)
    {
        try {
			$order = Order::find( $request->get('order_id') ) ;
            $data = $this->orderService->calcOrderReturnEarlyAmount($request, $order);
            return $this->successResponse($data, 'Tính toán thành công');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
		return $this->errorResponse('Có lỗi xảy ra', 500);
    }
   
	public function calc_order_before_complete(Request $request) {
		try {
			$order = Order::find( $request->get('order_id') ) ;

			$outdate_details = CarRentalHelper::writeMoneyOutDateAndTotal($order, $request);
			$early_details = CarRentalHelper::whenOrderReturnEarly($order, $request);

            return $this->successResponse(['success' => true, 'outdate_details' => $outdate_details, 'early_details' => $early_details ], 'Tính toán thành công');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
		return $this->errorResponse('Có lỗi xảy ra', 500);
	}

	public function check_timezone(Request $request) {
		$timeZone = DB::connection()->getDriverName() === 'pgsql'
			? DB::select("SELECT current_setting('TIMEZONE') as global_timezone, current_setting('TIMEZONE') as session_timezone")
			: DB::select("SELECT @@global.time_zone as global_timezone, @@session.time_zone as session_timezone");
		$currentTime = DB::select("SELECT NOW() as current_time");

		$data = [
			'php' => [
				'laravel_timezone' => config('app.timezone'),
				'current_time' => now(),
				'default_timezone' => date_default_timezone_get(),
				'current_date' => date('Y-m-d H:i:s'),
			],
			'mysql' => [
				'global_timezone' => $timeZone[0]->global_timezone,
				'session_timezone' => $timeZone[0]->session_timezone,
				'current_time' => $currentTime[0]->current_time
			]
		];
		return $this->successResponse($data, 'Thành công');
	}

	public function closeDeposit(Request $request) {
		try {
			$data = $this->orderService->closeDeposit($request);
            return $this->successResponse($data, 'Thanh lý hợp đồng thành công');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
		return $this->errorResponse('Có lỗi xảy ra', 500);
	}

	public function preview(Request $request) {
        \App\Support\PilotAccess::store(auth()->user(), $request->get('store_id'));
		try {
			if ($request->filled('store_id') && !HimotoStores::query()->whereKey((int) $request->get('store_id'))->exists()) {
				return $this->errorResponse('Kho/cơ sở không thuộc danh mục 6 kho HIMOTO.', 422);
			}
			$data = $request->all();
			$store = null;
			if ($request->has('store_id')) {
				$store = \App\Models\Store::find($request->get('store_id'));
			}
			$documentDto = \App\Http\Services\ContractDocumentBuilder::buildFromFormData($data, $store);
			return $this->successResponse($documentDto, 'Tạo bản xem trước hợp đồng thành công');
		} catch (\Exception $exception) {
			return $this->errorResponse($exception->getMessage(), 422);
		}
	}

	public function document(Order $order) {
        \App\Support\PilotAccess::store(auth()->user(), $order->store_id);
		try {
			$order->loadMissing(['customer', 'store', 'orderItems.vehicle', 'responsibleUser', 'transactions']);
			$documentDto = \App\Http\Services\ContractDocumentBuilder::buildFromOrder($order);
			return $this->successResponse($documentDto, 'Lấy tài liệu hợp đồng thành công');
		} catch (\Exception $exception) {
			return $this->errorResponse($exception->getMessage(), 422);
		}
	}

	public function lockContract(Order $order) {
        \App\Support\PilotAccess::store(auth()->user(), $order->store_id);
		try {
			$lockedOrder = $this->orderService->lockContract($order);
			return $this->successResponse(new OrderResource($lockedOrder), 'Chốt hợp đồng thành công');
		} catch (\Exception $exception) {
			return $this->errorResponse($exception->getMessage(), 422);
		}
	}
}
