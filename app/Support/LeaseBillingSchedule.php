<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaseBillingSchedule
{
    public static function assertAllowed(int $termMonths, string $cycle): void
    {
        $allowed = $termMonths === 6 ? ['day', 'week', 'month'] : ['week', 'month'];
        if (!in_array($cycle, $allowed, true)) {
            throw ValidationException::withMessages([
                'billing_cycle' => $termMonths === 6
                    ? 'Hợp đồng 6 tháng thanh toán theo ngày, tuần hoặc tháng.'
                    : 'Hợp đồng 12 và 24 tháng chỉ thanh toán theo tuần hoặc tháng.',
            ]);
        }
    }

    public static function paymentCount(int $termMonths, string $cycle): int
    {
        if ($cycle === 'day') {
            return $termMonths * 30;
        }
        if ($cycle === 'week') {
            return $termMonths * 4;
        }

        return $termMonths;
    }

    public static function endDate(Carbon $start, int $termMonths, string $cycle): Carbon
    {
        if ($cycle === 'day') {
            return $start->copy()->addDays(self::paymentCount($termMonths, $cycle));
        }
        if ($cycle === 'week') {
            return $start->copy()->addWeeks(self::paymentCount($termMonths, $cycle));
        }

        return $start->copy()->addMonthsNoOverflow($termMonths);
    }

    public static function dueDate(Carbon $start, string $cycle, int $index): Carbon
    {
        if ($cycle === 'day') {
            return $start->copy()->addDays($index);
        }
        if ($cycle === 'week') {
            return $start->copy()->addWeeks($index);
        }
        if ((int) $start->day === 30) {
            $cursor = $start->copy()->startOfMonth()->addMonthsNoOverflow($index);

            return $cursor->day(min(30, $cursor->daysInMonth));
        }

        return $start->copy()->addMonthsNoOverflow($index);
    }

    public static function cycleLabel(string $cycle): string
    {
        if ($cycle === 'day') {
            return 'ngày';
        }
        if ($cycle === 'week') {
            return 'tuần';
        }

        return 'tháng';
    }
}
