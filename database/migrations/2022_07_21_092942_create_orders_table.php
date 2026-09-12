<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
			$table->bigInteger('store_id');
			$table->string('customer_name');
			$table->string('customer_phone');
			$table->string('customer_address')->nullable();
			$table->string('customer_idnumber')->nullable();
			$table->string('vehicle_ids')->nullable();
			$table->string('order_type')->default('rental');
			$table->string('order_status')->default('pending');
			$table->datetime('rent_at');
			$table->datetime('return_at');
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
        Schema::dropIfExists('orders');
    }
}
