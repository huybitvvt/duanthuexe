<?php


namespace App\Http\Resources;


use App\Helpers\CarRentalHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'customer_name' => $this->customer ? $this->customer->name : '',
            'customer_phone' => $this->customer ? $this->customer->phone : '',
            'customer_id_card' => $this->customer ? $this->customer->id_card : '',
            'customer_address' => $this->customer ? $this->customer->address : '',
            'store' => $this->store,
            'vehicles' => $this->vehicles,
            'orderItems' => $this->orderItems,
            'note' => $this->note,
            'order_status' => $this->order_status,
            'pid' => $this->pid,
            'total' => $this->total,
            'addOnOrders' => $this->addOnOrders,
            'leads' => $this->leads,
            'out_date' => CarRentalHelper::converMinutesInDay($this->out_dated_at),
            'transactions' => $this->transactions,
            'activity_logs' => $this->activityLogs,
            'first_deposit_amount' => $this->first_deposit_amount,
            'additional_deposit_amount' => $this->additional_deposit_amount,
            'total_rental_fees' => $this->total_rental_fees,
            'created_without_collect_deposit' => $this->created_without_collect_deposit,
            'deposit_closed' => $this->deposit_closed,
        ];
    }
}
