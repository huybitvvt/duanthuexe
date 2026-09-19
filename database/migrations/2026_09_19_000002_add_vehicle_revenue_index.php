<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddVehicleRevenueIndex extends Migration
{
    // PostgreSQL cannot build or drop an index CONCURRENTLY inside a transaction.
    public $withinTransaction = false;

    public function up()
    {
        if (Schema::hasTable('order_vehicle_details')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS order_vehicle_details_rent_vehicle_idx ON order_vehicle_details (rent_at, vehicle_id) WHERE deleted_at IS NULL');
                return;
            }

            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->index(['rent_at', 'vehicle_id'], 'order_vehicle_details_rent_vehicle_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('order_vehicle_details')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS order_vehicle_details_rent_vehicle_idx');
                return;
            }

            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->dropIndex('order_vehicle_details_rent_vehicle_idx');
            });
        }
    }
}
