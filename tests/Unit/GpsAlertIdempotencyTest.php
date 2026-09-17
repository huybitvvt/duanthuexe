<?php

namespace Tests\Unit;

use App\Http\Services\Gps\GpsService;
use App\Http\Services\Gps\SandboxGpsProvider;
use App\Models\GpsAlert;
use App\Models\GpsDevice;
use App\Models\GpsPosition;
use App\Models\Store;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GpsAlertIdempotencyTest extends TestCase
{
    protected $gpsService;
    protected $sandboxProvider;
    protected $deviceStale;
    protected $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->sandboxProvider = new SandboxGpsProvider();
        $this->gpsService = new GpsService($this->sandboxProvider);

        $store = Store::create(['name' => 'Cơ sở Test', 'status' => 'active']);

        $this->vehicle = Vehicle::create([
            'plate_number' => '29B1-STALE',
            'store_id' => $store->id,
            'status' => 'rented',
        ]);

        $this->deviceStale = GpsDevice::create([
            'vehicle_id' => $this->vehicle->id,
            'provider' => 'sandbox_gps',
            'external_device_id' => 'DEV-STALE-01',
            'mapping_status' => 'active',
        ]);
    }

    public function test_stale_alert_opened_and_idempotent_across_repeated_polls(): void
    {
        // 1. First poll: device is stale (last ping 5 hours ago)
        $this->gpsService->syncDeviceLocation($this->deviceStale);

        $alerts = GpsAlert::where('gps_device_id', $this->deviceStale->id)->get();
        $this->assertCount(1, $alerts);
        $this->assertEquals('opened', $alerts->first()->status);
        $this->assertEquals('stale', $alerts->first()->alert_type);

        // 2. Second poll: device remains stale
        $this->gpsService->syncDeviceLocation($this->deviceStale);

        // Alert count must remain 1 (no spamming duplicate opened alerts)
        $this->assertEquals(1, GpsAlert::where('gps_device_id', $this->deviceStale->id)->count());
        $this->assertEquals(1, GpsAlert::where('gps_device_id', $this->deviceStale->id)->where('status', 'opened')->count());
    }

    public function test_stale_alert_auto_resolves_when_device_reconnects(): void
    {
        // 1. Initial stale sync opens alert
        $this->gpsService->syncDeviceLocation($this->deviceStale);
        $this->assertEquals(1, GpsAlert::where('gps_device_id', $this->deviceStale->id)->where('status', 'opened')->count());

        // 2. Simulate device reconnection: sends fresh ping with speed > 0
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $this->sandboxProvider->simulatedDevices['DEV-STALE-01'] = [
            'device_id' => 'DEV-STALE-01',
            'latitude' => 21.028500,
            'longitude' => 105.854200,
            'speed' => 25.0,
            'ignition' => true,
            'last_ping_at' => $now->toDateTimeString(),
            'status' => 'moving',
            'provider' => 'sandbox_gps',
        ];

        $this->gpsService->syncDeviceLocation($this->deviceStale);

        // Alert should now be automatically resolved
        $alert = GpsAlert::where('gps_device_id', $this->deviceStale->id)->first();
        $this->assertNotNull($alert);
        $this->assertEquals('resolved', $alert->status);
        $this->assertNotNull($alert->resolved_at);
        $this->assertStringContainsString('Tự động đóng', $alert->notes);
    }

    protected function createTestTables(): void
    {
        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('plate_number')->nullable();
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
            $table->string('mapping_status', 30)->default('active');
            $table->dateTime('last_sync_at')->nullable();
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
    }
}
