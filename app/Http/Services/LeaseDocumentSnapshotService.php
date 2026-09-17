<?php

namespace App\Http\Services;

use App\Models\LeaseContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaseDocumentSnapshotService
{
    /**
     * Create an immutable legal document snapshot for a lease contract.
     * Snapshot is captured once and permanently locked.
     */
    public function captureSnapshot(LeaseContract $contract, ?int $actorUserId = null): array
    {
        // 1. If already locked, do not overwrite to guarantee legal immutability
        if ($contract->document_snapshot_locked_at && !empty($contract->document_snapshot)) {
            return [
                'snapshot' => $contract->document_snapshot,
                'hash' => $contract->document_snapshot_hash,
                'version' => $contract->document_snapshot_version,
                'locked_at' => $contract->document_snapshot_locked_at,
            ];
        }

        $contract->loadMissing(['customer', 'vehicle', 'store']);

        $customer = $contract->customer;
        $vehicle = $contract->vehicle;
        $store = $contract->store;

        $totalAmount = (float) $contract->total_amount;
        $depositAmount = (float) $contract->deposit_amount;
        $periodAmount = (float) $contract->period_amount;

        $snapshot = [
            'contract_code' => $contract->contract_code,
            'version' => '1.0',
            'created_at' => Carbon::now('Asia/Ho_Chi_Minh')->toIso8601String(),
            'parties' => [
                'lessor' => [
                    'company_name' => 'CÔNG TY TNHH HIMOTO VIỆT NAM',
                    'brand_name' => 'HIMOTO',
                    'store_id' => $store ? $store->id : null,
                    'store_name' => $store ? $store->store_name : 'Cơ sở Himoto',
                    'address' => $store ? ($store->address ?? 'Hà Nội') : 'Hà Nội',
                ],
                'lessee' => [
                    'customer_id' => $customer ? $customer->id : null,
                    'name' => $customer ? ($customer->name ?? $customer->customer_name ?? 'Khách hàng') : 'Khách hàng',
                    'phone' => $customer ? ($customer->phone ?? $customer->customer_phone ?? '') : '',
                    'id_card' => $customer ? ($customer->id_card ?? $customer->identity_card ?? '') : '',
                    'id_card_date' => $customer ? ($customer->id_card_date ?? '') : '',
                    'id_card_place' => $customer ? ($customer->id_card_place ?? '') : '',
                    'address' => $customer ? ($customer->address ?? '') : '',
                ],
            ],
            'vehicle' => [
                'vehicle_id' => $vehicle ? $vehicle->id : null,
                'name' => $vehicle ? ($vehicle->name ?? $vehicle->vehicle_name ?? 'Xe máy điện') : 'Xe máy điện',
                'license_plate' => $vehicle ? ($vehicle->license ?: ($vehicle->license_plate ?: ($vehicle->plate_number ?: ''))) : '',
                'chassis_number' => $vehicle ? ($vehicle->chassis ?: ($vehicle->chassis_number ?: ($vehicle->frame_number ?: ''))) : '',
                'engine_number' => $vehicle ? ($vehicle->engine ?: ($vehicle->engine_number ?: '')) : '',
                'color' => $vehicle ? ($vehicle->color ?: '') : '',
            ],
            'financial_terms' => [
                'start_date' => $contract->start_date ? $contract->start_date->toDateString() : '',
                'end_date' => $contract->end_date ? $contract->end_date->toDateString() : '',
                'installment_count' => (int) $contract->installment_count,
                'total_amount' => $totalAmount,
                'total_amount_in_words' => self::numberToWordsVietnamese($totalAmount),
                'deposit_amount' => $depositAmount,
                'deposit_amount_in_words' => self::numberToWordsVietnamese($depositAmount),
                'period_amount' => $periodAmount,
                'period_amount_in_words' => self::numberToWordsVietnamese($periodAmount),
                'discount_amount' => (float) ($contract->discount_amount ?? 0),
            ],
            'meta' => [
                'logo_asset_version' => 'himoto-logo-v1',
                'signer_user_id' => $contract->assigned_user_id,
                'notes' => $contract->notes ?? '',
            ],
        ];

        $jsonString = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $jsonString);
        $lockedAt = Carbon::now('Asia/Ho_Chi_Minh');

        $contract->document_snapshot = $snapshot;
        $contract->document_snapshot_hash = $hash;
        $contract->document_snapshot_version = '1.0';
        $contract->document_snapshot_locked_at = $lockedAt;
        $contract->save();

        AuditService::log(
            'lease.contract.snapshot_created',
            $contract,
            null,
            ['contract_code' => $contract->contract_code, 'hash' => $hash, 'version' => '1.0'],
            "Tạo bản chụp snapshot hợp đồng thuê sở hữu {$contract->contract_code}",
            $contract->store_id,
            $actorUserId
        );

        return [
            'snapshot' => $snapshot,
            'hash' => $hash,
            'version' => '1.0',
            'locked_at' => $lockedAt,
        ];
    }

    /**
     * Convert monetary number to Vietnamese words.
     */
    public static function numberToWordsVietnamese(float $amount): string
    {
        $amount = round($amount);
        if ($amount == 0) {
            return 'Không đồng';
        }

        $digits = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

        $blocks = [];
        $temp = $amount;
        while ($temp > 0) {
            $blocks[] = $temp % 1000;
            $temp = (int) ($temp / 1000);
        }

        $res = [];
        for ($i = count($blocks) - 1; $i >= 0; $i--) {
            $b = $blocks[$i];
            if ($b == 0) {
                continue;
            }

            $hundred = (int) ($b / 100);
            $ten = (int) (($b % 100) / 10);
            $one = $b % 10;

            $bText = [];
            if ($hundred > 0 || count($res) > 0) {
                $bText[] = $digits[$hundred] . ' trăm';
            }

            if ($ten > 1) {
                $bText[] = $digits[$ten] . ' mươi';
                if ($one == 1) {
                    $bText[] = 'mốt';
                } elseif ($one == 5) {
                    $bText[] = 'lăm';
                } elseif ($one > 0) {
                    $bText[] = $digits[$one];
                }
            } elseif ($ten == 1) {
                $bText[] = 'mười';
                if ($one == 5) {
                    $bText[] = 'lăm';
                } elseif ($one > 0) {
                    $bText[] = $digits[$one];
                }
            } elseif ($ten == 0 && $one > 0) {
                if ($hundred > 0 || count($res) > 0) {
                    $bText[] = 'lẻ';
                }
                $bText[] = $digits[$one];
            }

            if (!empty($bText)) {
                $unitText = $units[$i] ?? '';
                $res[] = implode(' ', $bText) . ($unitText ? ' ' . $unitText : '');
            }
        }

        $text = trim(implode(' ', $res)) . ' đồng';
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, null, 'UTF-8');
    }
}
