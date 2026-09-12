<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCollumnVehicleCostPriceInSellOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sell_order_items', function (Blueprint $table) {
            $table->float('vehicle_cost_price', 16);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sell_order_items', function (Blueprint $table) {
            $table->dropColumn('vehicle_cost_price');
        });
    }
}
