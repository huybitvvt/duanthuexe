<?php

namespace Tests\Unit;

use App\Entities\Role;
use App\Http\Services\Gps\GpsService;
use App\Http\Services\Gps\SandboxGpsProvider;
use App\Models\GpsDevice;
use App\Models\GpsPosition;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GpsProviderContractTest extends TestCase
{
    protected $gpsService;
    protected $sandboxProvider;
    protected $store1;
    protected $store2;
    protected $staffStore1;
    protected $vehicle1;
    protected $vehicle2;
    protected $device1;
    protected $device2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->sandboxProvider = new SandboxGpsProvider();
        $this->gpsService = new GpsService($this->sandboxProvider);

        $adminRole = Role::create(['name' => 'Quản trị viên', 'slug' => 'quan-tri-vien']);
        $staffRole = Role::create(['name' => 'Nhân viên cơ sở', 'slug' => 'nhan-vien']);

        $this->store1 = Store::create(['name' => 'Cơ sở 1 - Cầu Giấy', 'status' => 'active']);
        $this->store2 = Store::create(['name' => 'Cơ sở 2 - Đống Đa', 'status' => 'active']);

        $this->staffStore1 = User::create([
            'name' => 'Nhân viên Cầu Giấy',
            'email' => 'staff_cg@himoto.vn',
            'role_id' => $staffRole->id,
            'store_id' => $this->store1->id,
            'is_admin' => 0,
            'status' => 1,
        ]);

        $this->vehicle1 = Vehicle::create([
            'plate_number' => '29B1-11111',
            'store_id' => $this->store1->id,
            'status' => 'rented',
        ]);

        $this->vehicle2 = Vehicle::create([
            'plate_number' => '29D1-22222',
            'store_id' => $this->store2->id,
            'status' => 'rented',
        ]);

        $this->device1 = GpsDevice::create([
            'vehicle_id' => $this->vehicle1->id,
            'provider' => 'sandbox_gps',
            'external_device_id' => 'DEV-ONLINE-01',
            'mapping_status' => 'active',
        ]);

        $this->device2 = GpsDevice::create([
            'vehicle_id' => $this->vehicle2->id,
            'provider' => 'sandbox_gps',
            'external_device_id' => 'DEV-STOPPED-01',
            'mapping_status' => 'active',
        ]);
    }

    public function test_sandbox_fixtures_and_status_normalization(): void
    {
        $online = $this->sandboxProvider->getVehicleLocation('DEV-ONLINE-01');
        $this->assertEquals('moving', $online['normalized_status']);
        $this->assertTrue($online['speed'] > 0);
        $this->assertTrue($online['ignition']);

        $stopped = $this->sandboxProvider->getVehicleLocation('DEV-STOPPED-01');
        $this->assertEquals('stopped', $stopped['normalized_status']);
        $this->assertEquals(0, $stopped['speed']);

        $stale = $this->sandboxProvider->getVehicleLocation('DEV-STALE-01');
        $this->assertEquals('stale_offline', $stale['normalized_status']);

        $never = $this->sandboxProvider->getVehicleLocation('DEV-NEVER-01');
        $this->assertEquals('never_connected', $never['normalized_status']);
        $this->assertNull($never['latitude']);
    }

    public function test_rejection_of_zero_coordinates(): void
    {
        $deviceZero = GpsDevice::create([
            'vehicle_id' => $this->vehicle1->id,
            'provider' => 'sandbox_gps',
            'external_device_id' => 'DEV-ZERO-COORDS',
            'mapping_status' => 'active',
        ]);

        // Inject 0,0 location
        $this->sandboxProvider->simulatedDevices['DEV-ZERO-COORDS'] = [
            'device_id' => 'DEV-ZERO-COORDS',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'speed' => 0,
            'last_ping_at' => now()->toDateTimeString(),
        ];

        $pos = $this->gpsService->syncDeviceLocation($deviceZero);
        $this->assertNull($pos);
        $this->assertEquals(0, GpsPosition::where('gps_device_id', $deviceZero->id)->count());
    }

    public function test_duplicate_poll_does_not_create_duplicate_positions(): void
    {
        // First sync
        $pos1 = $this->gpsService->syncDeviceLocation($this->device1);
        $this->assertNotNull($pos1);
        $this->assertEquals(1, GpsPosition::where('gps_device_id', $this->device1->id)->count());

        // Second sync with identical provider ping
        $pos2 = $this->gpsService->syncDeviceLocation($this->device1);
        $this->assertEquals($pos1->id, $pos2->id);
        $this->assertEquals(1, GpsPosition::where('gps_device_id', $this->device1->id)->count());
    }

    public function test_store_scope_isolation_for_gps_history(): void
    {
        $this->gpsService->syncDeviceLocation($this->device2);

        // Staff from Store 1 cannot view GPS history of Vehicle in Store 2
        $this->expectException(AuthorizationException::class);
        $this->gpsService->getDeviceHistory($this->device2->id, $this->staffStore1);
    }

    protected function createTestTables(): void
    {
        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->integer('role_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('is_admin')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('store_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('license')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('status')->default('ready');
            $table->timestamps();
        });

        Schema::dropIfExists('gps_devices');
        Schema::create('gps_devices', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('provider', 50)->default('sandbox');
            $table->string('external_device_id', 100);
            $table->string('imei', 50)->nullable();
            $table->string('sim_phone', 30)->nullable();
            $table->string('mapping_status', 30)->default('active');
            $table->dateTime('last_sync_at')->nullable();
            $table->text('device_metadata')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('gps_positions');
        Schema::create('gps_positions', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('gps_device_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 6, 2)->default(0);
            $table->boolean('ignition')->default(false);
            $table->decimal('heading', 6, 2)->nullable();
            $table->dateTime('provider_recorded_at');
            $table->dateTime('received_at')->nullable();
            $table->string('normalized_status', 30)->default('unknown');
            $table->text('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('gps_alerts');
        Schema::create('gps_alerts', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('gps_device_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('alert_type', 50);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 30)->default('opened');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('gps_recovery_actions');
        Schema::create('gps_recovery_actions', function ($table) {
            $table->increments('id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('gps_device_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('recovery_plan')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}
