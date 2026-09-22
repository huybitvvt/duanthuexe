<?php

namespace App\Support;

use Carbon\Carbon;

final class RentalPricing
{
    public const FLAT_DAILY_SCHEME = 'flat_200k_day';
    public const FLAT_DAILY_RATE = 200000;

    public static function flatDailyAmount($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            return 0;
        }

        $seconds = $startAt->diffInSeconds($endAt);
        return (int) ceil($seconds / 86400) * self::FLAT_DAILY_RATE;
    }
}
