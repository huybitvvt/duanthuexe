<?php

namespace Tests\Unit;

use App\Support\LeaseBillingSchedule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LeaseBillingScheduleTest extends TestCase
{
    public function test_six_month_daily_term_uses_fixed_thirty_day_months()
    {
        $start = Carbon::parse('2026-01-30');
        $this->assertSame(180, LeaseBillingSchedule::paymentCount(6, 'day'));
        $this->assertSame('2026-07-29', LeaseBillingSchedule::endDate($start, 6, 'day')->toDateString());
        $this->assertSame('2026-01-31', LeaseBillingSchedule::dueDate($start, 'day', 1)->toDateString());
    }

    public function test_monthly_due_day_stays_on_the_30th()
    {
        $start = Carbon::parse('2026-01-30');
        $this->assertSame('2026-02-28', LeaseBillingSchedule::dueDate($start, 'month', 1)->toDateString());
        $this->assertSame('2026-03-30', LeaseBillingSchedule::dueDate($start, 'month', 2)->toDateString());
    }

    public function test_week_count_follows_four_weeks_per_month()
    {
        $this->assertSame(24, LeaseBillingSchedule::paymentCount(6, 'week'));
        $this->assertSame(48, LeaseBillingSchedule::paymentCount(12, 'week'));
        $this->assertSame(96, LeaseBillingSchedule::paymentCount(24, 'week'));
        $this->assertSame('tuần', LeaseBillingSchedule::cycleLabel('week'));
    }
}
