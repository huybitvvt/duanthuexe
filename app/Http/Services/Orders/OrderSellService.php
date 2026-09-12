<?php

namespace App\Http\Services\Orders;

use App\Interfaces\ICrud;
use App\Repositories\SellOrderRepository;

class OrderSellService implements ICrud
{
    private $sellOrderRepository;

    public function __construct(SellOrderRepository $sellOrderRepository)
    {
        $this->sellOrderRepository = $sellOrderRepository;
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function index(array $params, $all = false)
    {
        $list = $this->sellOrderRepository->with(['orderItems.vehicle', 'customer', 'store'])
            ->filter($params);
        if ($all) {
            return $list->get();
        }
        return $list->orderBy('id', 'DESC')->paginate(config('app.paginate'));
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function store(array $params)
    {
        return $this->sellOrderRepository->create($params);
    }

    /**
     * @param $items
     * @return array
     */
    public function report($items)
    {
        $items->map(function ($value) {
            $value->total_vehicle_in_order = $value->orderItems->count();
            $value->total_price = $value->orderItems->sum('price');
            $value->total_cost = $value->orderItems->sum('vehicle.cost_price');
            return $value;
        });
        $total_vehicle_in_order = $items->sum('total_vehicle_in_order');
        $total_profit = $items->sum('price_profit');
        $total_price = $items->sum('price');
        $total_cost = $items->sum('total_cost');

        return [
            'total_sell' => $total_vehicle_in_order, // Số lượt bán
            'total_price' => $total_price, // Tổng thu
            'total_cost' => $total_cost, // Phí đầu tư
            'total_profit' => $total_profit // Lợi nhuận
        ];
    }

    public function update($id, array $params)
    {
        return $this->sellOrderRepository->update($params, $id);
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }

    public function all()
    {
        // TODO: Implement all() method.
    }
}
