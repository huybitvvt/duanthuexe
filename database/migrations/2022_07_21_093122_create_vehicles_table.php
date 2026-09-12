<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehiclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
			$table->string('name');
			$table->string('brand')->default('honda');
			$table->string('type', 25);
			$table->integer('year');
			$table->bigInteger('store_id');
			$table->string('license');
			$table->string('chassis')->nullable();
			$table->string('engine')->nullable();
			$table->string('status')->deafault('available');
			$table->string('cost_price')->nullable();
			$table->string('sale_price')->nullable();
			$table->string('price_range')->nullable();
			$table->bigInteger('created_by');
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
        Schema::dropIfExists('vehicles');
    }
}
