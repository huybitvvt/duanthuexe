<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrderVehicleDetailsTableAddColumnMoneyOutDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $table->double('money_out_date', 18, 2)->default(0)->after('return_at')->comment('Số tiền quá hạn');
            $table->integer('minute_out_date')->default(0)->after('money_out_date')->comment('Số phút quá hạn');
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
            $table->dropColumn('money_out_date', 'minute_out_date');
        });
    }
}
