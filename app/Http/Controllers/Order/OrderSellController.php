<?php

namespace App\Http\Controllers\Order;

use App\Entities\SellOrder;
use App\Http\Controllers\Controller;
use App\Http\Services\CustomerService;
use App\Http\Services\Orders\OrderSellItemService;
use App\Http\Services\Orders\OrderSellService;
use App\Http\Services\VehicleService;
use App\Models\Vehicle;
use http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderSellController extends Controller
{
    private $orderSellService;
    private $orderSellItemService;
    private $customerService;
    private $vehicleService;

    public function __construct(OrderSellService     $orderSellService,
                                OrderSellItemService $orderSellItemService,
                                CustomerService      $customerService,
                                VehicleService       $vehicleService
    )
    {
        $this->orderSellService = $orderSellService;
        $this->orderSellItemService = $orderSellItemService;
        $this->customerService = $customerService;
        $this->vehicleService = $vehicleService;
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->orderSellService->index($request->all()));
    }

    public function report(Request $request)
    {
        $result = $this->orderSellService->index($request->all(), true);
        return $this->successResponse($this->orderSellService->report($result));
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $items = $request->items;
            $customer = $request->customer;
            foreach ($items as $item) {
                if (!$this->vehicleService->checkStatusOtherSold($item['vehicle_id'])) {
                    return $this->errorResponse('Có xe đã bị bán trong danh sách của bạn', 400);
                }
            }
            $customer = $this->customerService->checkExistCustomer($customer['id_card']);
            if (!$customer) {
                $requestCustomer = new \Illuminate\Http\Request();
                $requestCustomer->replace($request->customer);
                $customer = $this->customerService->store($requestCustomer);
            }
            data_set($request, 'customer_id', $customer->id);
            $sellOrder = $this->orderSellService->store($request->all());
            if (!$sellOrder) {
                return $this->errorResponse('Tạo đơn hàng thất bại', 400);
            }
            $this->storeMultipleOrderItem($items, $sellOrder->id);
            $this->updateStatusVehicle($items, Vehicle::STATUS_SOLD);
            DB::commit();
            return $this->successResponse($sellOrder);
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }

    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id;
            $items = $request->order_items;
            $customer = $request->customer;
//            foreach ($items as $item) {
//                if (!$this->vehicleService->checkStatusOtherSold($item['vehicle_id'])) {
//                    return $this->errorResponse('Có xe đã bị bán trong danh sách của bạn', 400);
//                }
//            }
            $customer = $this->customerService->checkExistCustomer($customer['id_card']);
            if (!$customer) {
                $requestCustomer = new \Illuminate\Http\Request();
                $requestCustomer->replace($request->customer);
                $customer = $this->customerService->store($requestCustomer);
            }
            data_set($request, 'customer_id', $customer->id);
            $sellOrder = $this->orderSellService->update($id, $request->all());
            if (!$sellOrder) {
                return $this->errorResponse('Tạo đơn hàng thất bại', 400);
            }
            $this->orderSellItemService->deleteMultiple($id);
            $this->storeMultipleOrderItem($items, $sellOrder->id);
            $this->updateStatusVehicle($items, Vehicle::STATUS_SOLD);
            DB::commit();
            return $this->successResponse($sellOrder, 'Cập nhật đơn hàng thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    private function storeMultipleOrderItem($items, $order_id)
    {
        foreach ($items as $item) {
            data_set($item, 'order_id', $order_id);
            $this->orderSellItemService->store($item);
        }
        return true;
    }

    private function updateStatusVehicle($items, $status)
    {
        foreach ($items as $item) {
            $this->vehicleService->updateStatus($item['vehicle_id'], $status);
        }
        return true;
    }

    public function destroy(SellOrder $sellOrder)
    {
        $sellOrder->orderItems()->delete();
        $sellOrder->delete();
        return $this->successResponse(true, 'Xóa đơn hàng thành công');

    }
}
