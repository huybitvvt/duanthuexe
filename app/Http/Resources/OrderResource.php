<?php


namespace App\Http\Resources;


use App\Helpers\CarRentalHelper;
use App\Models\Transaction;
use Carbon\Carbon;
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
            'contract_number' => $this->contract_number ?: data_get($this->contract_snapshot, 'contract_number'),
            'draft_reference' => $this->draft_reference,
            'order_mode' => $this->order_mode,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'guardian_id_card' => $this->guardian_id_card,
            'contract_issued_at' => ($this->contract_issued_at ?: data_get($this->contract_snapshot, 'issued_at')) ? Carbon::parse($this->contract_issued_at ?: data_get($this->contract_snapshot, 'issued_at'))->format('d-m-Y H:i:s') : null,
            'contract_signed_on' => $this->contract_signed_on ? Carbon::parse($this->contract_signed_on)->format('d-m-Y') : null,
            'contract_responsible_user_id' => $this->contract_responsible_user_id,
            'contract_authorization_date' => $this->contract_authorization_date ? Carbon::parse($this->contract_authorization_date)->format('d-m-Y') : null,
            'contract_authorization_party_name' => $this->contract_authorization_party_name,
            'contract_collateral_description' => $this->contract_collateral_description,
            'contract_signer_a_name' => $this->contract_signer_a_name,
            'contract_signer_b_name' => $this->contract_signer_b_name,
            'customer_source' => $this->customer_source,
            'customer_source_url' => $this->customer_source_url,
            'contract_snapshot' => $this->contract_snapshot,
            'return_signer_a_name' => $this->return_signer_a_name,
            'return_signer_b_name' => $this->return_signer_b_name,
            'return_additional_note' => $this->return_additional_note,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : '',
            'customer_name' => $this->customer ? $this->customer->name : '',
            'customer_phone' => $this->customer ? $this->customer->phone : '',
            'customer_id_card' => $this->customer ? $this->customer->id_card : '',
            'customer_address' => $this->customer ? $this->customer->address : '',
            'customer_id_card_issued_on' => $this->customer && $this->customer->id_card_issued_on ? Carbon::parse($this->customer->id_card_issued_on)->format('d-m-Y') : '',
            'customer_id_card_issued_by' => $this->customer ? $this->customer->id_card_issued_by : '',
            'customer_relatives' => $this->customer ? $this->customer->relatives : [],
            'store' => $this->store,
            'vehicles' => $this->vehicles,
            'orderItems' => $this->orderItems,
            'note' => $this->note,
            'order_status' => $this->order_status,
            'pid' => $this->pid,
            'total' => $this->total,
            'addOnOrders' => $this->whenLoaded('addOnOrders'),
            'leads' => $this->leads,
            'out_date' => CarRentalHelper::converMinutesInDay($this->out_dated_at),
            'transactions' => $this->whenLoaded('transactions'),
            'activity_logs' => $this->whenLoaded('activityLogs'),
            'first_deposit_amount' => $this->first_deposit_amount,
            'additional_deposit_amount' => $this->additional_deposit_amount,
            'total_rental_fees' => $this->total_rental_fees,
            'created_without_collect_deposit' => $this->created_without_collect_deposit,
            'deposit_closed' => $this->deposit_closed,
            'contract_is_locked' => (bool) data_get($this->contract_snapshot, 'is_locked'),
            'vehicle_exchange_count' => (int) ($this->vehicle_exchange_count ?: 0),
        ];
    }
}
