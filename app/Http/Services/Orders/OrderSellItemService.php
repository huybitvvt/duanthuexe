<?php

namespace App\Http\Services\Orders;

use App\Entities\SellOrderItem;
use App\Interfaces\ICrud;
use App\Repositories\SellOrderItemRepository;

class OrderSellItemService implements ICrud
{
    private $orderItemRepository;

    public function __construct(SellOrderItemRepository $orderItemRepository)
    {
        $this->orderItemRepository = $orderItemRepository;
    }

    public function index(array $params)
    {
        // TODO: Implement index() method.
    }

    public function store(array $params)
    {
        return $this->orderItemRepository->create($params);
    }

    public function update($id, array $params)
    {
        // TODO: Implement update() method.
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }

    public function deleteMultiple($order_id)
    {
       return $this->orderItemRepository->where('order_id', $order_id)->delete();
    }

    public function all()
    {
        // TODO: Implement all() method.
    }
}
