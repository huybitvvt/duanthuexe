<?php

namespace App\Http\Services;

use App\Models\AccountingVatDocument;
use App\Models\BusinessAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function dashboard(array $filters): array
    {
        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();
        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.']);
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Khoảng báo cáo tối đa là 366 ngày.']);
        }

        $storeId = $filters['store_id'] ?? null;
        $vatQuery = AccountingVatDocument::query()
            ->with('store:id,store_name')
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->when($storeId, function ($query) use ($storeId) {
                return $query->where('store_id', $storeId);
            });
        $documents = (clone $vatQuery)->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->limit(200)->get();

        $inputVat = (float) (clone $vatQuery)->where('document_type', 'input')->sum('vat_amount');
        $outputVat = (float) (clone $vatQuery)->where('document_type', 'output')->sum('vat_amount');
        $assets = BusinessAsset::query()
            ->with('store:id,store_name')
            ->when($storeId, function ($query) use ($storeId) {
                return $query->where('store_id', $storeId);
            })
            ->orderBy('asset_code')
            ->limit(500)
            ->get()
            ->each(function ($asset) {
                $asset->append('monthly_depreciation');
            });

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'store_id' => $storeId,
            ],
            'summary' => [
                'input_vat' => $inputVat,
                'output_vat' => $outputVat,
                'vat_payable_estimate' => round($outputVat - $inputVat, 2),
                'asset_purchase_cost' => round((float) $assets->sum('purchase_cost'), 2),
                'asset_residual_value' => round((float) $assets->sum('residual_value'), 2),
                'monthly_depreciation' => round((float) $assets->sum('monthly_depreciation'), 2),
            ],
            'vat_documents' => $documents,
            'assets' => $assets,
        ];
    }

    public function saveVatDocument(array $data, int $userId, ?int $id = null): AccountingVatDocument
    {
        $beforeTax = round((float) $data['amount_before_tax'], 2);
        $rate = round((float) $data['vat_rate'], 2);
        $vatAmount = round($beforeTax * $rate / 100, 2);

        $payload = array_intersect_key($data, array_flip([
            'document_type', 'invoice_number', 'invoice_date', 'counterparty_name',
            'tax_code', 'payment_status', 'store_id', 'transaction_id', 'notes',
        ]));
        $payload['amount_before_tax'] = $beforeTax;
        $payload['vat_rate'] = $rate;
        $payload['vat_amount'] = $vatAmount;
        $payload['total_amount'] = round($beforeTax + $vatAmount, 2);
        $payload['created_by'] = $userId;

        return DB::transaction(function () use ($payload, $id) {
            $document = $id ? AccountingVatDocument::findOrFail($id) : new AccountingVatDocument();
            $document->fill($payload);
            $document->save();
            return $document->fresh(['store']);
        });
    }

    public function saveAsset(array $data, int $userId, ?int $id = null): BusinessAsset
    {
        $purchaseCost = round((float) $data['purchase_cost'], 2);
        $residualValue = round((float) ($data['residual_value'] ?? 0), 2);
        if ($residualValue > $purchaseCost) {
            throw ValidationException::withMessages([
                'residual_value' => 'Giá trị còn lại không được lớn hơn nguyên giá.',
            ]);
        }

        $payload = array_intersect_key($data, array_flip([
            'asset_code', 'name', 'category', 'store_id', 'purchase_date',
            'depreciation_months', 'status', 'notes',
        ]));
        $payload['purchase_cost'] = $purchaseCost;
        $payload['residual_value'] = $residualValue;
        $payload['created_by'] = $userId;

        return DB::transaction(function () use ($payload, $id) {
            $asset = $id ? BusinessAsset::findOrFail($id) : new BusinessAsset();
            $asset->fill($payload);
            $asset->save();
            return $asset->fresh(['store'])->append('monthly_depreciation');
        });
    }
}
