<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrderVehicleDetailTableAddColumnHandlerPrice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $table->double('handler_price', 18, 2)->default(0)->comment('Giá nhập tay');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $table->dropColumn('handler_price');
        });
    }
}
