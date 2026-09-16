<?php

namespace App\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractNumberService
{
    /**
     * Cấp số hợp đồng cho đơn hàng với tính chất idempotent (nếu đơn đã có số thì giữ nguyên).
     *
     * @param \App\Models\Order|null $order
     * @param Carbon|string|null $date
     * @return string
     */
    public static function generateForOrder(?\App\Models\Order $order = null, $date = null): string
    {
        if ($order && !empty($order->contract_number)) {
            return $order->contract_number;
        }
        if ($order && !empty($order->contract_snapshot['contract_number'])) {
            return $order->contract_snapshot['contract_number'];
        }

        return self::generate($date);
    }

    /**
     * Sinh số hợp đồng an toàn đa luồng theo định dạng YYYYMMDD-0001 (chuẩn Excel).
     * Múi giờ sử dụng: Asia/Ho_Chi_Minh.
     *
     * @param Carbon|string|null $date
     * @return string
     * @throws \Exception
     */
    public static function generate($date = null): string
    {
        $carbonDate = $date instanceof Carbon 
            ? $date->copy()->setTimezone('Asia/Ho_Chi_Minh') 
            : ($date ? Carbon::parse($date)->setTimezone('Asia/Ho_Chi_Minh') : Carbon::now('Asia/Ho_Chi_Minh'));

        $dateSql = $carbonDate->format('Y-m-d');
        $datePrefix = $carbonDate->format('Ymd');

        $driver = DB::connection()->getDriverName();
        $seq = 1;

        if ($driver === 'pgsql') {
            $result = DB::select(
                "INSERT INTO contract_number_counters (number_date, last_number, created_at, updated_at)
                 VALUES (?, 1, NOW(), NOW())
                 ON CONFLICT (number_date)
                 DO UPDATE SET last_number = contract_number_counters.last_number + 1, updated_at = NOW()
                 RETURNING last_number",
                [$dateSql]
            );

            if (!empty($result)) {
                $seq = (int) $result[0]->last_number;
            }
        } else {
            // Fallback cho MySQL / SQLite
            $counter = DB::table('contract_number_counters')
                ->where('number_date', $dateSql)
                ->lockForUpdate()
                ->first();

            if ($counter) {
                $seq = (int) $counter->last_number + 1;
                DB::table('contract_number_counters')
                    ->where('number_date', $dateSql)
                    ->update([
                        'last_number' => $seq,
                        'updated_at' => Carbon::now(),
                    ]);
            } else {
                $seq = 1;
                DB::table('contract_number_counters')->insert([
                    'number_date' => $dateSql,
                    'last_number' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        if ($seq > 9999) {
            throw new \Exception("Số lượng hợp đồng trong ngày {$datePrefix} đã đạt mức tối đa (9999). Vui lòng liên hệ quản trị viên.");
        }

        return sprintf('%s-%04d', $datePrefix, $seq);
    }

    /**
     * Kiểm tra tính hợp lệ của chuỗi số hợp đồng (Hỗ trợ chuẩn mới YYYYMMDD-0001 và chuẩn cũ YYYY/MM/DD-0001).
     *
     * @param string|null $contractNumber
     * @return bool
     */
    public static function isValid(?string $contractNumber): bool
    {
        if (empty($contractNumber)) {
            return false;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})-(\d{4})$/', $contractNumber, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $seq = (int) $matches[4];
            return ($seq >= 1 && $seq <= 9999) && checkdate($month, $day, $year);
        }

        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})-(\d{4})$/', $contractNumber, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $seq = (int) $matches[4];
            return ($seq >= 1 && $seq <= 9999) && checkdate($month, $day, $year);
        }

        return false;
    }
}
