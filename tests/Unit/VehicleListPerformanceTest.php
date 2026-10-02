<?php

namespace Tests\Unit;

use App\Http\Services\VehicleService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleListPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('stores', function ($t) { $t->increments('id'); $t->string('store_name'); });
        Schema::create('vehicles', function ($t) { $t->increments('id'); $t->integer('store_id'); $t->string('name'); $t->string('license'); $t->string('status'); $t->timestamps(); });
        Schema::create('files', function ($t) { $t->increments('id'); $t->string('provider'); $t->string('url'); $t->string('key')->nullable(); });
        Schema::create('vehicle_images', function ($t) { $t->integer('vehicle_id'); $t->integer('file_id'); });
        Schema::create('maintenance_types', function ($t) { $t->increments('id'); $t->string('name'); });
        Schema::create('maintenance_log', function ($t) { $t->increments('id'); $t->integer('vehicle_id'); $t->integer('maintenance_type_id'); $t->text('note'); });
        Schema::create('maintenance_vehicle', function ($t) { $t->increments('id'); $t->integer('vehicle_id'); });
        Schema::create('maintenance_schedules', function ($t) { $t->increments('id'); $t->integer('vehicle_id'); $t->integer('maintenance_type_id'); });
        DB::table('stores')->insert(['id' => 1, 'store_name' => 'CS1']);
        DB::table('maintenance_types')->insert(['id' => 1, 'name' => 'Bảo dưỡng']);
        for ($id = 1; $id <= 20; $id++) {
            DB::table('vehicles')->insert(['id' => $id, 'store_id' => 1, 'name' => 'Xe '.$id, 'license' => 'TEST-'.$id, 'status' => 'ready']);
            DB::table('files')->insert(['id' => $id, 'provider' => 'cloudinary', 'url' => 'https://fixture.invalid/'.$id.'.png']);
            DB::table('vehicle_images')->insert(['vehicle_id' => $id, 'file_id' => $id]);
            DB::table('maintenance_vehicle')->insert(['vehicle_id' => $id]);
            for ($index = 0; $index < 30; $index++) {
                DB::table('maintenance_log')->insert(['vehicle_id' => $id, 'maintenance_type_id' => 1, 'note' => str_repeat('fixture ', 40)]);
                DB::table('maintenance_schedules')->insert(['vehicle_id' => $id, 'maintenance_type_id' => 1]);
            }
        }
    }

    public function testListOmitsHistoriesButDetailKeepsThem(): void
    {
        $service = app(VehicleService::class);
        DB::enableQueryLog(); DB::flushQueryLog();
        $full = $service->index(['limit' => 20]);
        $fullQueries = count(DB::getQueryLog());
        $fullBytes = strlen($full->toJson());
        DB::flushQueryLog();
        $list = $service->index(['limit' => 20, 'list_view' => true]);
        $listQueries = count(DB::getQueryLog());
        $listBytes = strlen($list->toJson());
        $this->assertLessThan($fullQueries, $listQueries);
        $this->assertLessThanOrEqual(4, $listQueries);
        $this->assertLessThan($fullBytes / 5, $listBytes);
        $this->assertSame($full->pluck('id')->all(), $list->pluck('id')->all());
        $this->assertSame($full->first()->images->first()->url, $list->first()->images->first()->url);
        $this->assertFalse($list->first()->relationLoaded('maintenanceLog'));
        $detail = $service->show(Vehicle::findOrFail(1));
        $this->assertCount(30, $detail->maintenanceLog);
        $this->assertCount(30, $detail->maintenanceSchedule);
        $this->assertSame('CS1', $detail->store->store_name);
        fwrite(STDOUT, "\nVehicle list fixture: {$fullQueries} -> {$listQueries} queries, {$fullBytes} -> {$listBytes} JSON bytes.\n");
    }

    public function testDetailHttpRequiresAuthenticationAndViewPermission(): void
    {
        $this->getJson('/api/auth/vehicle/vehicles/1')->assertStatus(401);
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs((new User())->forceFill(['id' => 10, 'role' => 'nhan-vien', 'store_id' => 1, 'status' => 'active']), 'api');
        $detail = $this->getJson('/api/auth/vehicle/vehicles/1')->assertStatus(200);
        $this->assertSame(1, $detail->json('data.id'));
        $this->assertCount(30, $detail->json('data.maintenance_log'));
        $this->getJson('/api/auth/vehicle/vehicles/9999')->assertStatus(404);
        $this->actingAs((new User())->forceFill(['id' => 11, 'role' => 'khong-quyen', 'status' => 'active']), 'api');
        $this->getJson('/api/auth/vehicle/vehicles/1')->assertStatus(403);
    }
}
