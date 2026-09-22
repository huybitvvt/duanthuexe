<?php

namespace App\Http\Services;

use App\Models\LeaseContract;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class HimotoLegalDocumentService
{
    public function rentalHandover(Order $order): string
    {
        $order->loadMissing(['customer', 'orderItems.vehicle']);
        $vehicle = optional($order->orderItems->first())->vehicle;
        $handoverDate = optional($order->orderItems->first())->rent_at ?: $order->created_at;

        return view('documents.himoto-handover', [
            'code' => $order->contract_number ?: ($order->draft_reference ?: 'NHÁP-' . $order->id),
            'signedOn' => $this->date($order->contract_signed_on ?: $order->created_at),
            'handoverOn' => $this->date($handoverDate),
            'customer' => [
                'name' => optional($order->customer)->name,
                'address' => optional($order->customer)->address,
                'id_card' => optional($order->customer)->id_card,
                'id_card_date' => $this->date(optional($order->customer)->id_card_issued_on),
                'id_card_place' => optional($order->customer)->id_card_issued_by,
            ],
            'vehicles' => $order->orderItems->map(function ($item) {
                $vehicle = $item->vehicle;
                return $this->vehicleFields($vehicle);
            })->all(),
            'isDraft' => $order->order_status === 'draft',
        ])->render();
    }

    public function leaseHandover(LeaseContract $contract): string
    {
        $data = $this->leaseData($contract);
        return view('documents.himoto-handover', [
            'code' => $data['code'],
            'signedOn' => $data['signedOn'],
            'handoverOn' => $data['handoverOn'],
            'customer' => $data['customer'],
            'vehicles' => [$data['vehicle']],
            'isDraft' => false,
        ])->render();
    }

    public function leaseAnnex(LeaseContract $contract, ?int $overrideMonths = null): string
    {
        $data = $this->leaseData($contract);
        $months = $overrideMonths ?: (int) $data['months'];
        if (!in_array($months, [6, 12, 24], true)) {
            throw ValidationException::withMessages(['installment_count' => 'Chỉ có phụ lục SH06, SH12 hoặc SH24.']);
        }
        $data['months'] = $months;
        $data['variant'] = [6 => 'SH06', 12 => 'SH12', 24 => 'SH24'][$months];
        return view('documents.himoto-lease-annex', $data)->render();
    }

    private function leaseData(LeaseContract $contract): array
    {
        $contract->loadMissing(['customer', 'vehicle', 'originStore']);
        $snapshot = $contract->document_snapshot;
        if ($snapshot) {
            $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (!$contract->document_snapshot_hash || !hash_equals($contract->document_snapshot_hash, $hash)) {
                throw ValidationException::withMessages(['document_snapshot' => 'Snapshot hợp đồng không còn khớp checksum.']);
            }
        }

        $customer = $snapshot['parties']['lessee'] ?? [];
        $vehicle = $snapshot['vehicle'] ?? [];
        $terms = $snapshot['financial_terms'] ?? [];
        $assetValue = $snapshot
            ? (float) ($terms['asset_value'] ?? 0)
            : (float) optional($contract->vehicle)->sale_price;

        return [
            'code' => $snapshot['contract_code'] ?? $contract->contract_code,
            'signedOn' => $this->date($terms['start_date'] ?? $contract->start_date),
            'handoverOn' => $this->date($terms['start_date'] ?? $contract->start_date),
            'months' => $terms['installment_count'] ?? $contract->installment_count,
            'customer' => [
                'name' => $customer['name'] ?? optional($contract->customer)->name,
                'phone' => $customer['phone'] ?? optional($contract->customer)->phone,
                'address' => $customer['address'] ?? optional($contract->customer)->address,
                'id_card' => $customer['id_card'] ?? optional($contract->customer)->id_card,
                'id_card_date' => $this->date($customer['id_card_date'] ?? optional($contract->customer)->id_card_issued_on),
                'id_card_place' => $customer['id_card_place'] ?? optional($contract->customer)->id_card_issued_by,
            ],
            'vehicle' => [
                'brand' => optional($contract->vehicle)->brand,
                'name' => $vehicle['name'] ?? optional($contract->vehicle)->name,
                'license' => $vehicle['license_plate'] ?? optional($contract->vehicle)->license,
                'engine' => $vehicle['engine_number'] ?? optional($contract->vehicle)->engine,
                'chassis' => $vehicle['chassis_number'] ?? optional($contract->vehicle)->chassis,
            ],
            'businessAddress' => optional($contract->originStore)->store_address,
            'businessPhone' => optional($contract->originStore)->store_phone,
            // The annex asks for asset value, not total rental installments.
            'assetValue' => $assetValue > 0 ? number_format($assetValue, 0, ',', '.') : null,
            'assetValueWords' => $assetValue > 0 ? LeasePdfService::numberToVietnameseWords($assetValue) : null,
        ];
    }

    private function vehicleFields($vehicle): array
    {
        return [
            'brand' => optional($vehicle)->brand,
            'name' => optional($vehicle)->name,
            'license' => optional($vehicle)->license,
            'engine' => optional($vehicle)->engine,
            'chassis' => optional($vehicle)->chassis,
        ];
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : null;
    }
}
