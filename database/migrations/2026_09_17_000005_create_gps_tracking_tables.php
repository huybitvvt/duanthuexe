<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGpsTrackingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('gps_devices')) {
            Schema::create('gps_devices', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->string('provider', 50)->default('sandbox')->index();
                $table->string('external_device_id', 100)->index();
                $table->string('imei', 50)->nullable();
                $table->string('sim_phone', 30)->nullable();
                $table->string('mapping_status', 30)->default('active')->index(); // active, inactive, unmapped
                $table->timestamp('last_sync_at')->nullable();
                $table->text('device_metadata')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'external_device_id']);
            });
        }

        if (!Schema::hasTable('gps_positions')) {
            Schema::create('gps_positions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gps_device_id')->index();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('speed', 6, 2)->default(0);
                $table->boolean('ignition')->default(false);
                $table->decimal('heading', 6, 2)->nullable();
                $table->timestamp('provider_recorded_at')->index();
                $table->timestamp('received_at')->useCurrent();
                $table->string('normalized_status', 30)->default('unknown');
                $table->text('raw_payload')->nullable();
                $table->timestamps();

                $table->unique(['gps_device_id', 'provider_recorded_at']);
            });
        }

        if (!Schema::hasTable('gps_alerts')) {
            Schema::create('gps_alerts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gps_device_id')->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->string('alert_type', 50)->index(); // offline, stale, stopped, geofence, tamper
                $table->string('severity', 20)->default('warning'); // info, warning, critical
                $table->string('status', 30)->default('opened')->index(); // opened, acknowledged, resolved
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('acknowledged_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('gps_recovery_actions')) {
            Schema::create('gps_recovery_actions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('gps_device_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->text('recovery_plan')->nullable();
                $table->date('deadline')->nullable();
                $table->string('status', 30)->default('pending')->index(); // pending, in_progress, recovered, failed, cancelled
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gps_recovery_actions');
        Schema::dropIfExists('gps_alerts');
        Schema::dropIfExists('gps_positions');
        Schema::dropIfExists('gps_devices');
    }
}
