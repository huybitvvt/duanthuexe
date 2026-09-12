<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * Parse PostgreSQL timestamptz values returned with an offset and
 * microseconds while remaining compatible with Laravel 5.8's MySQL format.
 */
trait HandlesPostgresDates
{
    protected function asDateTime($value)
    {
        if (is_string($value)) {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        }

        return parent::asDateTime($value);
    }
}
