<?php

namespace Tests\Unit;

use App\Http\Services\VehicleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleRevenuePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('order_vehicle_details');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('stores');

        Schema::create('stores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('store_name');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->integer('year')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('license')->nullable();
            $table->string('status')->nullable();
            $table->string('cost_price')->nullable();
            $table->integer('type_of_service_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vehicle_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->dateTime('rent_at')->nullable();
            $table->decimal('total_money', 18, 2)->nullable();
            $table->decimal('handler_price', 18, 2)->default(0);
            $table->decimal('money_out_date', 18, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('stores')->insert([
            ['id' => 1, 'store_name' => 'Cửa hàng 1'],
            ['id' => 2, 'store_name' => 'Cửa hàng 2'],
        ]);

        $now = Carbon::parse('2026-09-19 10:00:00');
        $vehicles = [];
        for ($id = 1; $id <= 30; $id++) {
            $vehicles[] = [
                'id' => $id,
                'name' => 'Xe ' . $id,
                'brand' => 'honda',
                'type' => $id % 3 === 0 ? 'xeso' : 'xega',
                'year' => 2024,
                'store_id' => $id % 2 === 0 ? 2 : 1,
                'license' => 'TEST-' . $id,
                'status' => $id === 30 ? 'sold' : ($id % 2 === 0 ? 'using' : 'ready'),
                'cost_price' => '10000000',
                'type_of_service_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('vehicles')->insert($vehicles);

        $details = [];
        for ($vehicleId = 1; $vehicleId <= 30; $vehicleId++) {
            $details[] = [
                'vehicle_id' => $vehicleId,
                'order_id' => $vehicleId * 10,
                'rent_at' => '2026-09-10 08:00:00',
                'total_money' => $vehicleId * 100,
                'handler_price' => 0,
                'money_out_date' => 20,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $details[] = [
                'vehicle_id' => $vehicleId,
                'order_id' => $vehicleId * 10 + 1,
                'rent_at' => '2026-09-11 08:00:00',
                'total_money' => 999999,
                'handler_price' => $vehicleId * 200,
                'money_out_date' => 500,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $details[] = [
                'vehicle_id' => $vehicleId,
                'order_id' => $vehicleId * 10 + 2,
                'rent_at' => '2026-08-01 08:00:00',
                'total_money' => 500000,
                'handler_price' => 0,
                'money_out_date' => 0,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('order_vehicle_details')->insert($details);
    }

    public function test_revenue_is_aggregated_sorted_and_paginated_in_the_database(): void
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $result = app(VehicleService::class)->indexWithRevenue([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'sort_by' => 'revenue',
            'sort_type' => 'desc',
        ]);

        $this->assertSame(30, $result->total());
        $this->assertCount(20, $result->items());
        $this->assertLessThanOrEqual(3, $queryCount);

        $first = $result->items()[0];
        $this->assertSame(30, $first->id);
        $this->assertSame(2, (int) $first->count_order);
        $this->assertSame(9020.0, (float) $first->revenue);
        $this->assertTrue($first->relationLoaded('store'));
        $this->assertFalse($first->relationLoaded('orderVehicleDetails'));
    }

    public function test_vehicle_summary_uses_one_query_and_preserves_business_totals(): void
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $report = app(VehicleService::class)->reportByParams(['store_id' => 1]);

        $this->assertSame(1, $queryCount);
        $this->assertSame(15, $report['total_vehicle']);
        $this->assertSame(15, $report['total_vehicle_ready']);
        $this->assertSame(0, $report['total_vehicle_using']);
        $this->assertSame(10, $report['total_vehicle_ga']);
        $this->assertSame(5, $report['total_vehicle_so']);
        $this->assertSame(0, $report['total_vehicle_con']);
        $this->assertSame(150000000.0, $report['total_price']);
    }

    public function test_compact_vehicle_lookup_skips_heavy_relations(): void
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $result = app(VehicleService::class)->index([
            'name' => 'Xe 3',
            'compact' => true,
            'limit' => 5,
        ]);

        $this->assertLessThanOrEqual(3, $queryCount);
        $this->assertCount(2, $result->items());
        $vehicle = $result->items()[0];
        $this->assertTrue($vehicle->relationLoaded('store'));
        $this->assertFalse($vehicle->relationLoaded('images'));
        $this->assertFalse($vehicle->relationLoaded('maintenanceLog'));
        $this->assertFalse($vehicle->relationLoaded('maintenanceVehicle'));
        $this->assertFalse($vehicle->relationLoaded('maintenanceSchedule'));
    }

    public function test_revenue_export_query_skips_unused_store_relation(): void
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $result = app(VehicleService::class)->indexWithRevenue([
            'include_store' => false,
        ], true);

        $this->assertSame(1, $queryCount);
        $this->assertCount(30, $result);
        $this->assertFalse($result->first()->relationLoaded('store'));
    }
}
