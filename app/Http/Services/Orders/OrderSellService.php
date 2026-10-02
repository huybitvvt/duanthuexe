<?php

namespace App\Http\Services\Orders;

use App\Interfaces\ICrud;
use App\Repositories\SellOrderRepository;
use App\Entities\Customer;
use App\Entities\SellOrder;
use App\Entities\SellOrderItem;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
            $value->total_cost = $value->orderItems->sum('vehicle_cost_price');
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

    public function saveSale(array $data, array $items, ?int $id = null): SellOrder
    {
        return DB::transaction(function () use ($data, $items, $id) {
            $sale = $id ? SellOrder::lockForUpdate()->findOrFail($id) : new SellOrder();
            $previousItems = $id ? $sale->orderItems()->get()->keyBy('vehicle_id') : collect();
            $vehicleIds = array_map('intval', array_column($items, 'vehicle_id'));
            $lockedIds = array_unique(array_merge($vehicleIds, $previousItems->keys()->all()));
            $vehicles = Vehicle::whereIn('id', $lockedIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $savedItems = [];
            $total = 0;
            $totalCost = 0;
            foreach ($items as $item) {
                $vehicle = $vehicles->get((int) $item['vehicle_id']);
                $previous = $previousItems->get((int) $item['vehicle_id']);
                $expectedStatus = $previous ? Vehicle::STATUS_SOLD : Vehicle::STATUS_READY;
                if (!$vehicle || $vehicle->status !== $expectedStatus
                    || (int) ($vehicle->current_store_id ?: $vehicle->store_id) !== (int) $data['store_id']
                    || $this->hasOtherSale($vehicle->id, $id)
                    || $this->hasActiveContract($vehicle->id)) {
                    throw ValidationException::withMessages(['items' => 'Xe không sẵn sàng để bán tại cơ sở này hoặc đã thuộc hợp đồng khác.']);
                }
                $price = round((float) $item['price'], 2);
                $cost = (float) ($previous ? $previous->vehicle_cost_price : $vehicle->cost_price);
                $savedItems[] = ['vehicle_id' => $vehicle->id, 'price' => $price,
                    'vehicle_cost_price' => $cost, 'desc' => $item['desc'] ?? null];
                $total += $price;
                $totalCost += $cost;
            }
            $customer = Customer::where('id_card', $data['customer']['id_card'])->lockForUpdate()->first();
            if (!$customer) {
                $customer = Customer::create($data['customer']);
            }
            $sale->fill(['store_id' => $data['store_id'], 'sale_id' => $data['sale_id'] ?? null,
                'customer_id' => $customer->id, 'price' => $total, 'price_profit' => $total - $totalCost]);
            $sale->save();
            $sale->orderItems()->delete();
            foreach ($savedItems as $item) {
                $sale->orderItems()->create($item);
            }
            Vehicle::whereIn('id', $vehicleIds)->update(['status' => Vehicle::STATUS_SOLD]);
            $this->releaseSoldVehicles(array_diff($previousItems->keys()->all(), $vehicleIds));
            return $sale;
        });
    }

    public function deleteSale(int $id): void
    {
        DB::transaction(function () use ($id) {
            $sale = SellOrder::lockForUpdate()->findOrFail($id);
            $vehicleIds = $sale->orderItems()->pluck('vehicle_id')->all();
            Vehicle::whereIn('id', $vehicleIds)->orderBy('id')->lockForUpdate()->get();
            $sale->orderItems()->delete();
            $sale->delete();
            $this->releaseSoldVehicles($vehicleIds);
        });
    }

    private function hasOtherSale(int $vehicleId, ?int $exceptId = null): bool
    {
        return SellOrderItem::where('vehicle_id', $vehicleId)
            ->when($exceptId, function ($query) use ($exceptId) { $query->where('order_id', '!=', $exceptId); })
            ->exists();
    }

    private function hasActiveContract(int $vehicleId): bool
    {
        if (Schema::hasTable('orders') && Schema::hasTable('order_vehicle_details')
            && DB::table('order_vehicle_details')->join('orders', 'orders.id', '=', 'order_vehicle_details.order_id')
                ->where('vehicle_id', $vehicleId)->whereNull('orders.deleted_at')->whereNull('order_vehicle_details.deleted_at')
                ->where(function ($query) {
                    $query->whereNotIn('orders.order_status', ['completed', 'cancelled', 'draft'])->orWhereNull('orders.order_status');
                })->exists()) {
            return true;
        }
        return Schema::hasTable('lease_contracts') && DB::table('lease_contracts')
            ->where('vehicle_id', $vehicleId)->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'draft'])->exists();
    }

    private function releaseSoldVehicles(array $vehicleIds): void
    {
        foreach ($vehicleIds as $vehicleId) {
            if (!$this->hasOtherSale((int) $vehicleId) && !$this->hasActiveContract((int) $vehicleId)) {
                Vehicle::where('id', $vehicleId)->where('status', Vehicle::STATUS_SOLD)->update(['status' => Vehicle::STATUS_READY]);
            }
        }
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
