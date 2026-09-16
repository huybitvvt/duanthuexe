<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\Store;
use App\Models\Vehicle;
use App\Entities\Customer;
use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class ContractDocumentBuilder
{
    /**
     * Build DTO from uncommitted form input data (for Preview/In nháp).
     * Pure function: no DB mutations, no counter increment.
     *
     * @param array $data
     * @param Store|null $store
     * @return array
     */
    public static function buildFromFormData(array $data, ?Store $store = null): array
    {
        $signedDate = !empty($data['contract_signed_on'])
            ? self::parseCarbon($data['contract_signed_on'])
            : Carbon::now('Asia/Ho_Chi_Minh');

        $storeId = $data['store_id'] ?? null;
        if (!$store && $storeId) {
            $store = Store::find($storeId);
        }

        // Customer details
        $customerRelatives = [];
        if (!empty($data['customer_relatives'])) {
            $rawRel = is_string($data['customer_relatives'])
                ? json_decode($data['customer_relatives'], true)
                : $data['customer_relatives'];
            if (is_array($rawRel)) {
                $customerRelatives = array_values(array_filter($rawRel, function ($r) {
                    return !empty($r['name']) || !empty($r['phone']);
                }));
            }
        }

        // Vehicles details
        $vehiclesList = [];
        $rawOrderItems = $data['order_items'] ?? ($data['orderItems'] ?? []);
        $totalHats = 0;
        $totalRaincoats = 0;

        $firstRentAt = null;
        $firstReturnAt = null;

        if (is_array($rawOrderItems)) {
            foreach ($rawOrderItems as $idx => $item) {
                $vehicleId = $item['vehicle_id'] ?? ($item['vehicle']['id'] ?? null);
                $vehicle = null;
                if ($vehicleId) {
                    $vehicle = Vehicle::find($vehicleId);
                }

                $license = $item['license'] ?? ($item['vehicle']['license'] ?? ($vehicle ? $vehicle->license : ''));
                $vehicleName = $item['vehicle_name'] ?? ($item['vehicle']['name'] ?? ($vehicle ? $vehicle->name : ''));
                $vehicleType = $item['vehicle_type'] ?? ($item['vehicle']['type'] ?? ($vehicle ? $vehicle->type : ''));
                $color = $item['color'] ?? ($item['vehicle']['color'] ?? ($vehicle ? $vehicle->color : ''));
                $year = $item['year'] ?? ($item['vehicle']['year'] ?? ($vehicle ? $vehicle->year : ''));

                $hats = (int) ($item['borrow_hats'] ?? 0);
                $raincoats = (int) ($item['borrow_raincoats'] ?? 0);
                $totalHats += $hats;
                $totalRaincoats += $raincoats;

                $driverName = $item['driver_name'] ?? ($data['customer_name'] ?? '');
                $driverLicense = $item['driver_license_number'] ?? '';
                $driverLicenseIssuedOn = !empty($item['driver_license_issued_on'])
                    ? self::formatDateString($item['driver_license_issued_on'])
                    : '';

                $itemRentAt = !empty($item['rent_at']) ? self::parseCarbon($item['rent_at']) : null;
                $itemReturnAt = !empty($item['return_at']) ? self::parseCarbon($item['return_at']) : null;

                if (!$firstRentAt && $itemRentAt) {
                    $firstRentAt = $itemRentAt;
                }
                if (!$firstReturnAt && $itemReturnAt) {
                    $firstReturnAt = $itemReturnAt;
                }

                $vehiclesList[] = [
                    'vehicle_id' => $vehicleId,
                    'name' => $vehicleName,
                    'license' => $license,
                    'brand' => $vehicle ? ($vehicle->brand ?: '') : ($item['brand'] ?? ''),
                    'type_text' => self::mapVehicleType($vehicleType),
                    'color' => $color ?: '',
                    'year' => $year ?: '',
                    'driver_name' => $driverName,
                    'driver_license_number' => $driverLicense,
                    'driver_license_issued_on' => $driverLicenseIssuedOn,
                    'borrow_hats' => $hats,
                    'borrow_raincoats' => $raincoats,
                    'rent_at' => $itemRentAt ? $itemRentAt->format('d/m/Y H:i') : '',
                    'return_at' => $itemReturnAt ? $itemReturnAt->format('d/m/Y H:i') : '',
                ];
            }
        }

        // Responsible user
        $respUserName = $data['contract_responsible_user_name'] ?? '';
        if (empty($respUserName) && !empty($data['contract_responsible_user_id'])) {
            $user = \App\Models\User::find($data['contract_responsible_user_id']);
            if ($user) {
                $respUserName = $user->name;
            }
        }

        $depositAmount = (float) ($data['deposit_amount'] ?? ($data['total_deposit'] ?? 0));
        $rentalFee = (float) ($data['total_rental_fees'] ?? ($data['total'] ?? 0));

        $paymentMethod = (int) ($data['total_rental_payment_method'] ?? ($data['payment_method'] ?? 1));
        $paymentMethodText = self::getPaymentMethodLabel($paymentMethod);

        $authDate = !empty($data['contract_authorization_date'])
            ? self::formatDateString($data['contract_authorization_date'])
            : '';
        $authParty = $data['contract_authorization_party_name'] ?? '';

        $dto = [
            'is_preview' => true,
            'is_locked' => false,
            'contract_number' => 'Chưa cấp số',
            'contract_number_label' => 'BẢN XEM TRƯỚC - CHƯA CẤP SỐ',
            'signed_date' => [
                'day' => $signedDate ? $signedDate->format('d') : '.....',
                'month' => $signedDate ? $signedDate->format('m') : '.....',
                'year' => $signedDate ? $signedDate->format('Y') : '.........',
                'full_text' => $signedDate ? 'Hôm nay, ngày ' . $signedDate->format('d') . ' tháng ' . $signedDate->format('m') . ' năm ' . $signedDate->format('Y') : 'Hôm nay, ngày..... tháng.... năm.............',
            ],
            'responsible_user' => [
                'id' => $data['contract_responsible_user_id'] ?? null,
                'name' => $respUserName,
            ],
            'lessor' => [
                'company_name' => config('contract.company_name', 'CÔNG TY CP THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM'),
                'tax_code' => config('contract.tax_code', '0110863055'),
                'representative_name' => config('contract.representative_name', 'Bà: Nguyễn Thu Thủy'),
                'representative_title' => config('contract.representative_title', 'Giám đốc'),
                'head_office' => config('contract.head_office', 'Sn 31 dãy C1 Tổ 28 Khu tập thể Đồng Bát, Bệnh viện 198 Bộ Công An, P. Từ Liêm, Tp. Hà Nội, VN'),
                'branch_name' => $store ? $store->store_name : 'Himoto Chi nhánh',
                'branch_address' => $store ? $store->store_address : '',
                'branch_phone' => $store ? $store->store_phone : '',
                'authorization' => [
                    'has_authorization' => !empty($authParty) || !empty($authDate),
                    'date' => $authDate,
                    'party_name' => $authParty,
                ],
            ],
            'customer' => [
                'name' => $data['customer_name'] ?? '',
                'phone' => $data['customer_phone'] ?? '',
                'address' => $data['customer_address'] ?? '',
                'id_card' => $data['customer_id_card'] ?? '',
                'id_card_issued_on' => !empty($data['customer_id_card_issued_on']) ? self::formatDateString($data['customer_id_card_issued_on']) : '',
                'id_card_issued_by' => $data['customer_id_card_issued_by'] ?? '',
                'relatives' => $customerRelatives,
                'relatives_text' => self::formatRelativesText($customerRelatives),
            ],
            'vehicles' => $vehiclesList,
            'vehicles_count' => count($vehiclesList),
            'primary_vehicle' => count($vehiclesList) > 0 ? $vehiclesList[0] : [
                'name' => '',
                'license' => '',
                'brand' => '',
                'type_text' => '',
                'color' => '',
                'year' => '',
                'driver_name' => '',
                'driver_license_number' => '',
                'driver_license_issued_on' => '',
                'borrow_hats' => 0,
                'borrow_raincoats' => 0,
            ],
            'rent_time' => self::buildRentTimeStructure($firstRentAt, $firstReturnAt),
            'pricing' => [
                'unit_price' => $data['unit_price'] ?? null,
                'unit_price_text' => !empty($data['unit_price']) ? number_format($data['unit_price'], 0, ',', '.') . ' đ/ngày' : 'Theo bảng giá',
                'package_name' => $data['package_name'] ?? 'Theo ngày',
                'total_rent_fee' => $rentalFee,
                'total_rent_fee_formatted' => number_format($rentalFee, 0, ',', '.') . ' đ',
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
                'paid_amount_formatted' => number_format((float) ($data['paid_amount'] ?? 0), 0, ',', '.') . ' đ',
                'payment_method_text' => $paymentMethodText,
            ],
            'deposit' => [
                'deposit_amount' => $depositAmount,
                'deposit_amount_formatted' => number_format($depositAmount, 0, ',', '.') . ' đ',
                'collateral_description' => $data['contract_collateral_description'] ?? '',
                'payment_method_text' => self::getPaymentMethodLabel((int) ($data['deposit_payment_method'] ?? 1)),
            ],
            'equipment' => [
                'total_hats' => $totalHats,
                'total_raincoats' => $totalRaincoats,
                'helmet_penalty_amount' => config('contract.helmet_penalty_amount', 70000),
            ],
            'signers' => [
                'signer_a_name' => $data['contract_signer_a_name'] ?? ($respUserName ?: ($store ? $store->store_name : 'Đại diện Bên A')),
                'signer_b_name' => $data['contract_signer_b_name'] ?? ($data['customer_name'] ?? ''),
            ],
            'return_confirmation' => [
                'is_returned' => false,
                'return_hour' => '.....',
                'return_minute' => '.....',
                'return_day' => '.....',
                'return_month' => '.....',
                'return_year' => '202.....',
                'actual_return_time_formatted' => '',
                'signer_a_name' => '',
                'signer_b_name' => '',
                'refund_amount' => 0,
                'refund_amount_formatted' => '',
                'additional_note' => '',
            ],
        ];

        return $dto;
    }

    /**
     * Build DTO from committed Order & its locked or generated snapshot.
     *
     * @param Order $order
     * @return array
     */
    public static function buildFromOrder(Order $order): array
    {
        $snapshot = is_array($order->contract_snapshot) ? $order->contract_snapshot : [];
        $isLocked = !empty($snapshot['is_locked']);

        // Signed documents never fall back to mutable customer/store records.
        $store = $isLocked ? null : $order->store;
        $customer = $isLocked ? null : $order->customer;

        $signedDate = !empty($order->contract_signed_on)
            ? self::parseCarbon($order->contract_signed_on)
            : ($order->created_at ? self::parseCarbon($order->created_at) : Carbon::now('Asia/Ho_Chi_Minh'));

        $contractNumber = $order->contract_number ?: ($snapshot['contract_number'] ?? 'Chưa cấp số');
        if (!empty($snapshot['signed_on'])) {
            $signedDate = self::parseCarbon($snapshot['signed_on']);
        }

        // Customer
        $customerName = $snapshot['customer']['name'] ?? ($customer ? $customer->name : '');
        $customerPhone = $snapshot['customer']['phone'] ?? ($customer ? $customer->phone : '');
        $customerAddress = $snapshot['customer']['address'] ?? ($customer ? $customer->address : '');
        $customerIdCard = $snapshot['customer']['id_card'] ?? ($customer ? $customer->id_card : '');
        $customerIdCardIssuedOn = !empty($snapshot['customer']['id_card_issued_on'])
            ? self::formatDateString($snapshot['customer']['id_card_issued_on'])
            : ($customer && $customer->id_card_issued_on ? self::formatDateString($customer->id_card_issued_on) : '');
        $customerIdCardIssuedBy = $snapshot['customer']['id_card_issued_by'] ?? ($customer ? $customer->id_card_issued_by : '');

        $rawRel = $snapshot['customer']['relatives'] ?? ($customer ? $customer->relatives : []);
        $customerRelatives = [];
        if (is_string($rawRel)) {
            $customerRelatives = json_decode($rawRel, true) ?: [];
        } elseif (is_array($rawRel)) {
            $customerRelatives = $rawRel;
        }

        // Vehicles
        $vehiclesList = [];
        $totalHats = 0;
        $totalRaincoats = 0;
        $firstRentAt = null;
        $firstReturnAt = null;

        if (!empty($snapshot['vehicles']) && is_array($snapshot['vehicles'])) {
            foreach ($snapshot['vehicles'] as $v) {
                $hats = (int) ($v['borrow_hats'] ?? 0);
                $raincoats = (int) ($v['borrow_raincoats'] ?? 0);
                $totalHats += $hats;
                $totalRaincoats += $raincoats;

                $rAt = !empty($v['rent_at']) ? self::parseCarbon($v['rent_at']) : null;
                $rtAt = !empty($v['return_at']) ? self::parseCarbon($v['return_at']) : null;
                if (!$firstRentAt && $rAt) $firstRentAt = $rAt;
                if (!$firstReturnAt && $rtAt) $firstReturnAt = $rtAt;

                $vehiclesList[] = [
                    'vehicle_id' => $v['vehicle_id'] ?? null,
                    'name' => $v['vehicle_name'] ?? ($v['name'] ?? ''),
                    'license' => $v['license'] ?? '',
                    'brand' => $v['brand'] ?? '',
                    'type_text' => self::mapVehicleType($v['type'] ?? ''),
                    'color' => $v['color'] ?? '',
                    'year' => $v['year'] ?? '',
                    'driver_name' => $v['driver_name'] ?? $customerName,
                    'driver_license_number' => $v['driver_license_number'] ?? '',
                    'driver_license_issued_on' => !empty($v['driver_license_issued_on']) ? self::formatDateString($v['driver_license_issued_on']) : '',
                    'borrow_hats' => $hats,
                    'borrow_raincoats' => $raincoats,
                    'rent_at' => $rAt ? $rAt->format('d/m/Y H:i') : '',
                    'return_at' => $rtAt ? $rtAt->format('d/m/Y H:i') : '',
                ];
            }
        } elseif (!$isLocked) {
            // Read from relationship orderItems
            $order->loadMissing(['orderItems.vehicle']);
            foreach ($order->orderItems as $item) {
                $v = $item->vehicle;
                $hats = (int) ($item->borrow_hats ?? 0);
                $raincoats = (int) ($item->borrow_raincoats ?? 0);
                $totalHats += $hats;
                $totalRaincoats += $raincoats;

                $rAt = !empty($item->rent_at) ? self::parseCarbon($item->rent_at) : null;
                $rtAt = !empty($item->return_at) ? self::parseCarbon($item->return_at) : null;
                if (!$firstRentAt && $rAt) $firstRentAt = $rAt;
                if (!$firstReturnAt && $rtAt) $firstReturnAt = $rtAt;

                $vehiclesList[] = [
                    'vehicle_id' => $v ? $v->id : null,
                    'name' => $v ? $v->name : '',
                    'license' => $v ? $v->license : '',
                    'brand' => $v ? ($v->brand ?: '') : '',
                    'type_text' => self::mapVehicleType($v ? $v->type : ''),
                    'color' => $v ? $v->color : '',
                    'year' => $v ? $v->year : '',
                    'driver_name' => $item->driver_name ?: $customerName,
                    'driver_license_number' => $item->driver_license_number ?: '',
                    'driver_license_issued_on' => !empty($item->driver_license_issued_on) ? self::formatDateString($item->driver_license_issued_on) : '',
                    'borrow_hats' => $hats,
                    'borrow_raincoats' => $raincoats,
                    'rent_at' => $rAt ? $rAt->format('d/m/Y H:i') : '',
                    'return_at' => $rtAt ? $rtAt->format('d/m/Y H:i') : '',
                ];
            }
        }

        $depositAmount = (float) ($snapshot['payment']['deposit_amount'] ?? ($order->first_deposit_amount ?? 0));
        $rentalFee = (float) ($snapshot['payment']['rental_fees'] ?? ($order->total ?? 0));
        $receipts = collect($snapshot['payment']['transactions_summary'] ?? []);
        $paidAmount = (float) ($snapshot['payment']['paid_amount'] ?? $receipts->where('type', 'in')->where('name', 'order:rental_fees')->sum('value'));
        $rentalReceipts = $receipts->where('type', 'in')->where('name', 'order:rental_fees');
        $rentalMethods = $rentalReceipts->pluck('payment_method')->map(function ($v) { return (int)$v; })->unique();
        $rentalMethod = $rentalMethods->count() > 1 || $rentalMethods->contains(3) ? 3 : ($rentalMethods->first() ?: 1);
        $unitPrice = count($vehiclesList) === 1 ? data_get($snapshot, 'vehicles.0.unit_price') : null;

        $respUserName = $snapshot['responsible_user']['name'] ?? ($order->responsibleUser ? $order->responsibleUser->name : '');

        $authDate = !empty($snapshot['authorization']['date'])
            ? self::formatDateString($snapshot['authorization']['date'])
            : (!empty($order->contract_authorization_date) ? self::formatDateString($order->contract_authorization_date) : '');
        $authParty = $snapshot['authorization']['party_name'] ?? ($order->contract_authorization_party_name ?? '');

        // Return confirmation
        $retSignerA = $snapshot['return_confirmation']['signer_a_name'] ?? ($order->return_signer_a_name ?? '');
        $retSignerB = $snapshot['return_confirmation']['signer_b_name'] ?? ($order->return_signer_b_name ?? '');
        $retNote = $snapshot['return_confirmation']['additional_note'] ?? ($order->return_additional_note ?? '');
        $completedAt = !empty($snapshot['return_confirmation']['completed_at'])
            ? self::parseCarbon($snapshot['return_confirmation']['completed_at'])
            : ($order->completed_at ? self::parseCarbon($order->completed_at) : null);

        $isReturned = in_array($order->order_status, ['completed', 'unpaid']) || !empty($completedAt);
        $refundPaid = $isReturned ? (float) $order->transactions()->where('type', 'out')->where('name', 'order:complete:' . $order->id)->sum('value') : 0;

        $dto = [
            'is_preview' => $contractNumber === 'Chưa cấp số',
            'is_locked' => $isLocked,
            'order_id' => $order->id,
            'contract_number' => $contractNumber,
            'contract_number_label' => $contractNumber === 'Chưa cấp số' ? 'BẢN XEM TRƯỚC - CHƯA CẤP SỐ' : $contractNumber,
            'issued_at' => $order->contract_issued_at ? Carbon::parse($order->contract_issued_at)->format('d/m/Y H:i') : null,
            'signed_date' => [
                'day' => $signedDate ? $signedDate->format('d') : '.....',
                'month' => $signedDate ? $signedDate->format('m') : '.....',
                'year' => $signedDate ? $signedDate->format('Y') : '.........',
                'full_text' => $signedDate ? 'Hôm nay, ngày ' . $signedDate->format('d') . ' tháng ' . $signedDate->format('m') . ' năm ' . $signedDate->format('Y') : 'Hôm nay, ngày..... tháng.... năm.............',
            ],
            'responsible_user' => [
                'id' => $order->contract_responsible_user_id,
                'name' => $respUserName,
            ],
            'lessor' => [
                'company_name' => $snapshot['lessor']['company_name'] ?? config('contract.company_name', 'CÔNG TY CP THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM'),
                'tax_code' => $snapshot['lessor']['tax_code'] ?? config('contract.tax_code', '0110863055'),
                'representative_name' => $snapshot['lessor']['representative_name'] ?? config('contract.representative_name', 'Bà: Nguyễn Thu Thủy'),
                'representative_title' => $snapshot['lessor']['representative_title'] ?? config('contract.representative_title', 'Giám đốc'),
                'head_office' => $snapshot['lessor']['head_office_address'] ?? ($snapshot['lessor']['head_office'] ?? config('contract.head_office')),
                'branch_name' => $snapshot['lessor']['branch_name'] ?? ($store ? $store->store_name : 'Himoto Chi nhánh'),
                'branch_address' => $snapshot['lessor']['branch_address'] ?? ($store ? $store->store_address : ''),
                'branch_phone' => $snapshot['lessor']['contact_phone'] ?? ($snapshot['lessor']['branch_phone'] ?? ($store ? $store->store_phone : '')),
                'authorization' => [
                    'has_authorization' => !empty($authParty) || !empty($authDate),
                    'date' => $authDate,
                    'party_name' => $authParty,
                ],
            ],
            'customer' => [
                'name' => $customerName,
                'phone' => $customerPhone,
                'address' => $customerAddress,
                'id_card' => $customerIdCard,
                'id_card_issued_on' => $customerIdCardIssuedOn,
                'id_card_issued_by' => $customerIdCardIssuedBy,
                'relatives' => $customerRelatives,
                'relatives_text' => self::formatRelativesText($customerRelatives),
            ],
            'vehicles' => $vehiclesList,
            'vehicles_count' => count($vehiclesList),
            'primary_vehicle' => count($vehiclesList) > 0 ? $vehiclesList[0] : [
                'name' => '',
                'license' => '',
                'brand' => '',
                'type_text' => '',
                'color' => '',
                'year' => '',
                'driver_name' => '',
                'driver_license_number' => '',
                'driver_license_issued_on' => '',
                'borrow_hats' => 0,
                'borrow_raincoats' => 0,
            ],
            'rent_time' => self::buildRentTimeStructure($firstRentAt, $firstReturnAt),
            'pricing' => [
                'unit_price' => $unitPrice,
                'unit_price_text' => $unitPrice !== null ? number_format($unitPrice, 0, ',', '.') . ' đ/' . (data_get($snapshot, 'vehicles.0.pricing_unit') ?: 'ngày') : 'Theo chi tiết từng xe',
                'package_name' => 'Theo ngày',
                'total_rent_fee' => $rentalFee,
                'total_rent_fee_formatted' => number_format($rentalFee, 0, ',', '.') . ' đ',
                'paid_amount' => $paidAmount,
                'paid_amount_formatted' => number_format($paidAmount, 0, ',', '.') . ' đ',
                'payment_method_text' => self::getPaymentMethodLabel((int) ($snapshot['payment']['rental_payment_method'] ?? $rentalMethod)),
            ],
            'deposit' => [
                'deposit_amount' => $depositAmount,
                'deposit_amount_formatted' => number_format($depositAmount, 0, ',', '.') . ' đ',
                'collateral_description' => $snapshot['payment']['collateral_description'] ?? ($isLocked ? '' : ($order->contract_collateral_description ?: '')),
                'payment_method_text' => self::getPaymentMethodLabel((int) ($snapshot['payment']['deposit_payment_method'] ?? 1)),
            ],
            'equipment' => [
                'total_hats' => $totalHats,
                'total_raincoats' => $totalRaincoats,
                'helmet_penalty_amount' => config('contract.helmet_penalty_amount', 70000),
            ],
            'signers' => [
                'signer_a_name' => $snapshot['signers']['signer_a_name'] ?? ($order->contract_signer_a_name ?: ($respUserName ?: ($store ? $store->store_name : 'Đại diện Bên A'))),
                'signer_b_name' => $snapshot['signers']['signer_b_name'] ?? ($order->contract_signer_b_name ?: $customerName),
            ],
            'return_confirmation' => [
                'is_returned' => $isReturned,
                'return_hour' => $completedAt ? $completedAt->format('H') : '.....',
                'return_minute' => $completedAt ? $completedAt->format('i') : '.....',
                'return_day' => $completedAt ? $completedAt->format('d') : '.....',
                'return_month' => $completedAt ? $completedAt->format('m') : '.....',
                'return_year' => $completedAt ? $completedAt->format('Y') : '202.....',
                'actual_return_time_formatted' => $completedAt ? $completedAt->format('d/m/Y H:i') : '',
                'signer_a_name' => $retSignerA,
                'signer_b_name' => $retSignerB,
                'refund_amount' => $refundPaid,
                'refund_amount_formatted' => number_format($refundPaid, 0, ',', '.') . ' đ',
                'additional_note' => $retNote,
            ],
        ];

        if ($isLocked && !empty($snapshot['document'])) {
            return array_replace($snapshot['document'], ['return_confirmation' => $dto['return_confirmation']]);
        }
        return $dto;
    }

    /**
     * Build date/time decomposition for rent duration.
     */
    protected static function buildRentTimeStructure(?Carbon $start, ?Carbon $end): array
    {
        return [
            'start' => [
                'hour' => $start ? $start->format('H') : '.....',
                'minute' => $start ? $start->format('i') : '.....',
                'day' => $start ? $start->format('d') : '.....',
                'month' => $start ? $start->format('m') : '.....',
                'year' => $start ? $start->format('Y') : '202.....',
                'formatted' => $start ? $start->format('d/m/Y H:i') : '',
            ],
            'end' => [
                'hour' => $end ? $end->format('H') : '.....',
                'minute' => $end ? $end->format('i') : '.....',
                'day' => $end ? $end->format('d') : '.....',
                'month' => $end ? $end->format('m') : '.....',
                'year' => $end ? $end->format('Y') : '202.....',
                'formatted' => $end ? $end->format('d/m/Y H:i') : '',
            ],
        ];
    }

    /**
     * Map internal vehicle type code to Vietnamese text.
     */
    public static function mapVehicleType(?string $type): string
    {
        switch ($type) {
            case 'xeso':
            case 'xe_so':
                return 'Xe số';
            case 'xega':
            case 'xe_ga':
                return 'Xe tay ga';
            case 'xecon':
            case 'xe_con':
                return 'Xe côn tay';
            case 'xe_dien':
                return 'Xe điện';
            default:
                return $type ? ucfirst($type) : 'Xe máy';
        }
    }

    /**
     * Extract brand from vehicle name if not provided.
     */
    public static function extractBrand(string $name): string
    {
        $nameLower = strtolower($name);
        if (strpos($nameLower, 'honda') !== false || strpos($nameLower, 'wave') !== false || strpos($nameLower, 'vision') !== false || strpos($nameLower, 'lead') !== false || strpos($nameLower, 'air blade') !== false || strpos($nameLower, 'sh') !== false) {
            return 'Honda';
        }
        if (strpos($nameLower, 'yamaha') !== false || strpos($nameLower, 'sirius') !== false || strpos($nameLower, 'exciter') !== false || strpos($nameLower, 'grande') !== false || strpos($nameLower, 'janus') !== false) {
            return 'Yamaha';
        }
        if (strpos($nameLower, 'vinfast') !== false || strpos($nameLower, 'feliz') !== false || strpos($nameLower, 'klara') !== false) {
            return 'VinFast';
        }
        if (strpos($nameLower, 'piaggio') !== false || strpos($nameLower, 'vespa') !== false) {
            return 'Piaggio';
        }
        return 'Honda';
    }

    /**
     * Format array of relatives into readable string.
     */
    public static function formatRelativesText(array $relatives): string
    {
        if (empty($relatives)) {
            return '';
        }
        $parts = [];
        foreach ($relatives as $rel) {
            if (empty($rel['name']) && empty($rel['phone'])) {
                continue;
            }
            $itemText = $rel['name'] ?? '';
            if (!empty($rel['relationship'])) {
                $itemText .= ' (' . $rel['relationship'] . ')';
            }
            if (!empty($rel['phone'])) {
                $itemText .= ': ' . $rel['phone'];
            }
            $parts[] = $itemText;
        }
        return implode(' - Và: ', $parts);
    }

    /**
     * Payment method label text.
     */
    public static function getPaymentMethodLabel(int $method): string
    {
        switch ($method) {
            case 1:
                return 'TM';
            case 2:
                return 'CK';
            case 3:
                return 'CK & TM';
            default:
                return 'TM';
        }
    }

    /**
     * Helper to safely parse Carbon instance across multiple string formats.
     */
    public static function parseCarbon($val): ?Carbon
    {
        if (empty($val)) {
            return null;
        }
        if ($val instanceof Carbon) {
            return $val;
        }
        try {
            if (is_string($val) && preg_match('~^\d{2}/\d{2}/\d{4}(?: |$)~', $val)) {
                $format = strlen($val) === 10 ? '!d/m/Y' : (strlen($val) === 16 ? '!d/m/Y H:i' : '!d/m/Y H:i:s');
                return Carbon::createFromFormat($format, $val, 'Asia/Ho_Chi_Minh');
            }
            return Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format a date string to d/m/Y safely.
     */
    public static function formatDateString($val): string
    {
        $c = self::parseCarbon($val);
        return $c ? $c->format('d/m/Y') : (string) $val;
    }
}
