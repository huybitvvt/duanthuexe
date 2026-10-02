<?php

namespace App\Http\Controllers\Order;

use App\Entities\SellOrder;
use App\Http\Controllers\Controller;
use App\Http\Services\Orders\OrderSellService;
use App\Validators\CustomerValidator;
use Illuminate\Http\Request;

class OrderSellController extends Controller
{
    private $orderSellService;

    public function __construct(OrderSellService $orderSellService)
    {
        $this->orderSellService = $orderSellService;
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
        $data = $this->validateSale($request, 'items');
        return $this->successResponse($this->orderSellService->saveSale($data, $data['items']));
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|integer|exists:sell_orders,id']);
        $data = $this->validateSale($request, 'order_items');
        return $this->successResponse($this->orderSellService->saveSale($data, $data['order_items'], (int) $request->id), 'Cập nhật đơn hàng thành công');
    }

    private function validateSale(Request $request, string $itemsKey): array
    {
        $rules = [
            'store_id' => 'required|integer|exists:stores,id',
            'sale_id' => 'nullable|integer|exists:users,id,deleted_at,NULL',
            'customer' => 'required|array',
            $itemsKey => 'required|array|min:1',
            $itemsKey.'.*.vehicle_id' => 'required|integer|distinct|exists:vehicles,id',
            $itemsKey.'.*.price' => 'required|numeric|min:0|max:1000000000000',
            $itemsKey.'.*.desc' => 'nullable|string|max:1000',
        ];
        foreach (CustomerValidator::rules() as $field => $rule) {
            $rules['customer.'.$field] = $rule;
        }
        $rules['customer.id_card'] = 'required|string|max:30';
        return $request->validate($rules);
    }

    public function destroy(SellOrder $sellOrder)
    {
        $this->orderSellService->deleteSale($sellOrder->id);
        return $this->successResponse(true, 'Xóa đơn hàng thành công');
    }
}
