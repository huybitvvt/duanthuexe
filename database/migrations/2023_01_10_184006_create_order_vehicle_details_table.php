<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateOrderVehicleDetailsTable.
 */
class CreateOrderVehicleDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('vehicle_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->bigInteger('price_id')->nullable();
            $table->integer('borrow_hats')->default(0)->comment('Số mũ mượn');
            $table->dateTime('rent_at')->nullable()->comment('Thời gian mượn');
            $table->dateTime('return_at')->nullable()->comment('Thời gian trả');
            $table->double('total_money', 18, 2)->nullable()->comment('Tổng số tiền thuê xe');
            $table->tinyInteger('status')->default(1)->comment('0: Ẩn; 1: Hiện');
            $table->string('type')->default('day')->comment('day: thuê theo ngày; total: Thuê trọn gói');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('order_vehicle_details');
    }
}
