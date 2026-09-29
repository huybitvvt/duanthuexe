<?php

namespace App\Http\Resources;

use App\Helpers\CarRentalHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    /**
     * Keep the paginated contract list small. Full contract, payment and
     * history data is loaded only when a user opens the detail/edit dialog.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number ?: data_get($this->contract_snapshot, 'contract_number'),
            'draft_reference' => $this->draft_reference,
            'order_mode' => $this->order_mode,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : '',
            'customer_name' => $this->customer ? $this->customer->name : '',
            'customer_phone' => $this->customer ? $this->customer->phone : '',
            'store' => $this->store,
            'vehicles' => $this->vehicles,
            'orderItems' => $this->orderItems,
            'note' => $this->note,
            'order_status' => $this->order_status,
            'pid' => $this->pid,
            'total' => $this->total,
            'leads' => $this->leads,
            'out_date' => CarRentalHelper::converMinutesInDay($this->out_dated_at),
            'first_deposit_amount' => $this->first_deposit_amount,
            'additional_deposit_amount' => $this->additional_deposit_amount,
            'created_without_collect_deposit' => $this->created_without_collect_deposit,
            'deposit_closed' => $this->deposit_closed,
            'vehicle_exchange_count' => (int) ($this->vehicle_exchange_count ?: 0),
        ];
    }
}
