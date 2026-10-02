<?php

namespace Tests\Feature\Himoto;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('vehicles', function ($table) {
            $table->increments('id'); $table->string('name'); $table->string('license')->nullable();
            $table->integer('store_id'); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('maintenance_types', function ($table) {
            $table->increments('id'); $table->string('name'); $table->string('note')->nullable(); $table->timestamps();
        });
        Schema::create('maintenance_schedules', function ($table) {
            $table->increments('id'); $table->integer('vehicle_id'); $table->integer('maintenance_type_id');
            $table->timestamp('next_time_manual')->nullable(); $table->timestamp('next_time_auto')->nullable(); $table->timestamps();
        });
        Schema::create('maintenance_rules', function ($table) {
            $table->increments('id'); $table->integer('maintenance_type_id'); $table->integer('value');
            $table->timestamp('next_time')->nullable(); $table->timestamps();
        });
        DB::table('vehicles')->insert([
            ['id' => 1, 'name' => 'CS1 fixture', 'store_id' => 31],
            ['id' => 2, 'name' => 'CS2 fixture', 'store_id' => 32],
        ]);
        DB::table('maintenance_types')->insert(['id' => 1, 'name' => 'QA maintenance']);
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs(new User(['id' => 1, 'role_id' => 1]), 'api');
    }

    public function testScheduleRequiresExistingVehicleTypeAndValidDate(): void
    {
        foreach ([[], ['vehicle_id' => 999, 'maintenance_type_id' => 1, 'next_time_manual' => '2026-10-02'],
            ['vehicle_id' => 1, 'maintenance_type_id' => 1, 'next_time_manual' => 'invalid-date']] as $payload) {
            $this->postJson('/api/auth/maintenance-schedules', $payload)->assertStatus(422);
        }
        $this->assertSame(0, DB::table('maintenance_schedules')->count());
    }

    public function testScheduleCanBeCreatedUpdatedAndClearedWithoutDuplicates(): void
    {
        $payload = ['vehicle_id' => 1, 'maintenance_type_id' => 1,
            'next_time_manual' => '2026-10-03 08:00:00', 'next_time_auto' => '2026-10-04 08:00:00'];
        $this->postJson('/api/auth/maintenance-schedules', $payload)->assertStatus(200);
        $payload['id'] = DB::table('maintenance_schedules')->value('id');
        $payload['next_time_manual'] = '2026-10-05 08:00:00';
        $payload['next_time_auto'] = null;
        $this->postJson('/api/auth/maintenance-schedules', $payload)->assertStatus(200);
        $this->assertSame(1, DB::table('maintenance_schedules')->count());
        $row = DB::table('maintenance_schedules')->first();
        $this->assertSame('2026-10-05 08:00:00', $row->next_time_manual);
        $this->assertNull($row->next_time_auto);
        $this->deleteJson('/api/auth/maintenance-schedules/'.$payload['id'])->assertStatus(200);
        $this->assertSame(0, DB::table('maintenance_schedules')->count());
    }

    public function testInvalidRuleBatchDoesNotSaveEarlierRows(): void
    {
        $this->postJson('/api/auth/maintenance-rules', [
            ['maintenance_type_id' => 1, 'value' => 30],
            ['maintenance_type_id' => 999, 'value' => -1],
        ])->assertStatus(422);
        $this->assertSame(0, DB::table('maintenance_rules')->count());
    }

    public function testUnknownRuleIdIsRejected(): void
    {
        $this->postJson('/api/auth/maintenance-rules', [
            ['id' => 999, 'maintenance_type_id' => 1, 'value' => 30],
        ])->assertStatus(422);
    }

    public function testUpcomingStoreFilterAppliesToManualAndAutoDates(): void
    {
        $now = \App\Helpers\DateTimeHelper::now()->toDateTimeString();
        DB::table('maintenance_schedules')->insert([
            ['vehicle_id' => 1, 'maintenance_type_id' => 1, 'next_time_manual' => null, 'next_time_auto' => $now],
            ['vehicle_id' => 2, 'maintenance_type_id' => 1, 'next_time_manual' => $now, 'next_time_auto' => null],
        ]);
        $response = $this->getJson('/api/auth/maintenance-schedules/upcoming?store_id=31')->assertStatus(200);
        $this->assertSame([31], array_map('intval', array_column($response->json('data'), 'store_id')));
    }
}
