<?php

namespace Tests\Unit;

use App\Http\Services\KpiReportService;
use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpiReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('leads');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('pending');
            $table->string('source_channel')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Tư vấn A']);
        DB::table('orders')->insert(['id' => 10, 'store_id' => 1]);
    }

    public function testReportAggregatesSourceCampaignStaffAndConversion()
    {
        Lead::create([
            'customer_phone' => '0901000001',
            'store_id' => 1,
            'user_id' => 1,
            'order_id' => 10,
            'status' => 'converted',
            'source_channel' => 'Facebook',
            'campaign_name' => 'Khai trương tháng 9',
            'created_at' => '2026-09-10 09:00:00',
        ]);
        Lead::create([
            'customer_phone' => '0901000002',
            'store_id' => 1,
            'user_id' => 1,
            'status' => 'pending',
            'source_channel' => 'Facebook',
            'campaign_name' => 'Khai trương tháng 9',
            'created_at' => '2026-09-11 09:00:00',
        ]);
        Lead::create([
            'customer_phone' => '0901000003',
            'store_id' => 2,
            'status' => 'pending',
            'utm_source' => 'Google',
            'utm_campaign' => 'Search Brand',
            'created_at' => '2026-09-12 09:00:00',
        ]);

        $report = app(KpiReportService::class)->build('2026-09-01', '2026-09-30', 1);

        $this->assertEquals(2, $report['summary']['total_leads']);
        $this->assertEquals(1, $report['summary']['converted_leads']);
        $this->assertEquals(50.0, $report['summary']['conversion_rate']);
        $this->assertEquals('Facebook', $report['by_source'][0]['label']);
        $this->assertEquals('Khai trương tháng 9', $report['by_campaign'][0]['label']);
        $this->assertEquals('Tư vấn A', $report['by_staff'][0]['label']);
        $this->assertCount(2, $report['daily']);
    }

    public function testReportSupportsLongDateRangeUpToThreeYears()
    {
        // 2025-01-20 to 2026-09-19 is ~608 days, which previously failed at > 366 days
        $report = app(KpiReportService::class)->build('2025-01-20', '2026-09-19', 1);
        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
    }

    public function testReportThrowsValidationExceptionWhenExceedingThreeYears()
    {
        try {
            app(KpiReportService::class)->build('2022-01-01', '2026-09-19', 1);
            $this->fail('Expected ValidationException was not thrown');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertEquals('Khoảng báo cáo tối đa là 3 năm (1095 ngày).', $e->errors()['end_date'][0]);
        }
    }
}
