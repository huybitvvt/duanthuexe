<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVehicleRevenueIndex extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_vehicle_details')) {
            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->index(['rent_at', 'vehicle_id'], 'order_vehicle_details_rent_vehicle_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('order_vehicle_details')) {
            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->dropIndex('order_vehicle_details_rent_vehicle_idx');
            });
        }
    }
}
