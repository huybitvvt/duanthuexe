<?php

namespace App\Support;

use App\Helpers\DateTimeHelper;
use App\Models\PriceVehicle;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CounterOrderPricing
{
    public static function validate(Request $request): void
    {
        $items = $request->input('order_items', []);
        if (!is_array($items) || !$items) {
            throw ValidationException::withMessages(['order_items' => 'Cần chọn xe và thời gian thuê.']);
        }

        $total = 0;
        $vehicleIds = [];
        foreach ($items as $item) {
            if (!is_array($item) || !empty($item['pricing_scheme'])
                || !empty($item['handler_price']) || !empty($item['substitute_unit_price'])
                || !empty($item['order_item_fees'])) {
                throw ValidationException::withMessages(['order_items' => 'Giá hoặc phí ngoài bảng cần trưởng phòng duyệt.']);
            }
            $vehicle = Vehicle::find((int) ($item['vehicle_id'] ?? 0));
            if ($vehicle && in_array((int) $vehicle->id, $vehicleIds, true)) {
                throw ValidationException::withMessages(['order_items' => 'Một xe chỉ được lập một lần trên đơn.']);
            }
            if ($vehicle) {
                $vehicleIds[] = (int) $vehicle->id;
            }
            if (empty($item['rent_at']) || empty($item['return_at'])) {
                throw ValidationException::withMessages(['order_items' => 'Cần nhập thời gian thuê và trả xe.']);
            }
            $rentAt = DateTimeHelper::parse($item['rent_at']);
            $returnAt = DateTimeHelper::parse($item['return_at']);
            if (!$vehicle || !$rentAt || !$returnAt || $returnAt->lte($rentAt)) {
                throw ValidationException::withMessages(['order_items' => 'Xe hoặc thời gian thuê không hợp lệ.']);
            }
            $minutes = $rentAt->diffInMinutes($returnAt);
            $days = (int) floor($minutes / 1440);
            $remainderHours = (int) floor(($minutes % 1440) / 60);
            $hourlyFee = 0;
            if ($remainderHours >= 8) {
                $days++;
            } elseif ($remainderHours > 0) {
                $hourlyFee = $remainderHours * (['xeso' => 15000, 'xega' => 15000,
                    'xecon' => 25000, 'sh' => 35000, 'xesh' => 35000][$vehicle->type] ?? 0);
            }
            $type = $item['type'] ?? 'day';
            if (!in_array($type, ['day', 'total'], true)) {
                throw ValidationException::withMessages(['order_items' => 'Kiểu giá thuê không hợp lệ.']);
            }
            // These range columns are strings in the original migration. Compare
            // numerically in PHP so PostgreSQL and SQLite use the same rule.
            $price = PriceVehicle::query()->where('type', $vehicle->type)
                ->where('price_type', $type)->orderBy('id')->get()->first(function ($row) use ($days, $vehicle) {
                    return (int) $row->from_date <= $days && (int) $row->to_date >= $days
                        && (int) $row->from_year <= (int) $vehicle->year
                        && (int) $row->to_year >= (int) $vehicle->year;
                });
            if (!$price) {
                throw ValidationException::withMessages(['order_items' => 'Chưa có giá niêm yết cho xe và thời gian thuê này.']);
            }
            $amount = $type === 'total' ? (float) $price->price : (float) $price->price * $days + $hourlyFee;
            // Older forms send the calculated rental fee in custom fields even
            // when it matches the tariff. Only a real override needs approval.
            foreach (['custom_total_money', 'custom_hiring_fee'] as $field) {
                $customAmount = $item[$field] ?? null;
                if ($customAmount !== null && $customAmount !== ''
                    && (!is_numeric($customAmount)
                        || ((float) $customAmount !== 0.0 && (float) $customAmount !== $amount))) {
                    throw ValidationException::withMessages(['order_items' => 'Giá hoặc phí ngoài bảng cần trưởng phòng duyệt.']);
                }
            }
            if ((float) ($item['total_money'] ?? -1) !== $amount) {
                throw ValidationException::withMessages(['order_items' => 'Tiền thuê từng xe phải khớp bảng giá hiện có.']);
            }
            $total += $amount;
        }

        if ($total <= 0 || (float) $request->input('total', -1) !== $total
            || (float) $request->input('total_rental_fees', -1) !== $total) {
            throw ValidationException::withMessages(['total_rental_fees' => 'Tổng tiền thuê phải khớp bảng giá hiện có.']);
        }

        $deposit = max(0, (float) $request->input('first_deposit_amount', 0));
        $additionalDeposit = max(0, (float) $request->input('additional_deposit_amount', 0));
        if ($additionalDeposit > 0) {
            throw ValidationException::withMessages(['additional_deposit_amount' => 'Thu thêm cọc chỉ thực hiện sau khi đơn đã phát hành.']);
        }
        $paid = (filter_var($request->input('create_order_without_input_deposit', false), FILTER_VALIDATE_BOOLEAN) ? 0 : $deposit)
            + (filter_var($request->input('create_order_without_input_rental_fee', false), FILTER_VALIDATE_BOOLEAN) ? 0 : $total)
            + $additionalDeposit;
        if ((float) $request->input('pid', -1) !== (float) $paid) {
            throw ValidationException::withMessages(['pid' => 'Số đã thu phải khớp các khoản thanh toán của đơn.']);
        }
    }
}
