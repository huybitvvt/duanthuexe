<?php

namespace Tests\Benchmark;

use App\Http\Services\VehicleService;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleRevenueBenchmarkTest extends TestCase
{
    public function testSqlAggregationAgainstLegacyHydration(): void
    {
        $this->createSchema();
        $this->seedRows(400, 50);
        $params = ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'];

        $optimizedQueryCount = 0;
        DB::listen(function () use (&$optimizedQueryCount) {
            $optimizedQueryCount++;
        });
        $optimizedMemoryStart = memory_get_usage(true);
        $optimizedStartedAt = microtime(true);
        $optimized = app(VehicleService::class)->indexWithRevenue($params);
        $optimizedMs = (microtime(true) - $optimizedStartedAt) * 1000;
        $optimizedMemory = memory_get_usage(true) - $optimizedMemoryStart;
        $optimizedIds = collect($optimized->items())->pluck('id')->all();

        $legacyQueryCountStart = $optimizedQueryCount;
        $legacyMemoryStart = memory_get_usage(true);
        $legacyStartedAt = microtime(true);
        $legacy = Vehicle::query()
            ->with(['orderVehicleDetails' => function ($query) {
                $query->whereBetween('rent_at', ['2026-09-01 00:00:00', '2026-09-30 23:59:59']);
            }])
            ->get()
            ->map(function ($vehicle) {
                $vehicle->revenue = $vehicle->orderVehicleDetails->sum(function ($detail) {
                    return (float) $detail->handler_price !== 0.0
                        ? (float) $detail->handler_price
                        : (float) $detail->total_money + (float) $detail->money_out_date;
                });
                return $vehicle;
            })
            ->sortByDesc('revenue')
            ->values()
            ->slice(0, 20);
        $legacyMs = (microtime(true) - $legacyStartedAt) * 1000;
        $legacyMemory = memory_get_usage(true) - $legacyMemoryStart;
        $legacyQueryCount = $optimizedQueryCount - $legacyQueryCountStart;

        fwrite(STDOUT, PHP_EOL . json_encode([
            'dataset' => ['vehicles' => 400, 'rental_rows' => 20000],
            'optimized' => [
                'milliseconds' => round($optimizedMs, 2),
                'memory_mb' => round($optimizedMemory / 1048576, 2),
                'queries' => $legacyQueryCountStart,
                'hydrated_vehicles' => count($optimizedIds),
                'hydrated_rental_rows' => 0,
            ],
            'legacy' => [
                'milliseconds' => round($legacyMs, 2),
                'memory_mb' => round($legacyMemory / 1048576, 2),
                'queries' => $legacyQueryCount,
                'hydrated_vehicles' => 400,
                'hydrated_rental_rows' => 20000,
            ],
        ], JSON_PRETTY_PRINT) . PHP_EOL);

        $this->assertSame($legacy->pluck('id')->all(), $optimizedIds);
    }

    private function createSchema(): void
    {
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
            $table->integer('vehicle_id')->nullable()->index();
            $table->integer('order_id')->nullable();
            $table->dateTime('rent_at')->nullable();
            $table->decimal('total_money', 18, 2)->nullable();
            $table->decimal('handler_price', 18, 2)->default(0);
            $table->decimal('money_out_date', 18, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function seedRows(int $vehicleCount, int $rowsPerVehicle): void
    {
        DB::table('stores')->insert(['id' => 1, 'store_name' => 'Benchmark']);
        $now = Carbon::parse('2026-09-19 10:00:00');

        $vehicles = [];
        for ($vehicleId = 1; $vehicleId <= $vehicleCount; $vehicleId++) {
            $vehicles[] = [
                'id' => $vehicleId,
                'name' => 'Xe ' . $vehicleId,
                'brand' => 'honda',
                'type' => 'xega',
                'year' => 2024,
                'store_id' => 1,
                'license' => 'BENCH-' . $vehicleId,
                'status' => 'ready',
                'cost_price' => '10000000',
                'type_of_service_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($vehicles, 50) as $chunk) {
            DB::table('vehicles')->insert($chunk);
        }

        $details = [];
        for ($vehicleId = 1; $vehicleId <= $vehicleCount; $vehicleId++) {
            for ($row = 1; $row <= $rowsPerVehicle; $row++) {
                $details[] = [
                    'vehicle_id' => $vehicleId,
                    'order_id' => ($vehicleId * 1000) + $row,
                    'rent_at' => '2026-09-10 08:00:00',
                    'total_money' => ($vehicleId * 10) + $row,
                    'handler_price' => 0,
                    'money_out_date' => 20,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($details) === 80) {
                    DB::table('order_vehicle_details')->insert($details);
                    $details = [];
                }
            }
        }
        if ($details) {
            DB::table('order_vehicle_details')->insert($details);
        }
    }
}
