<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTablePricingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pricing', function (Blueprint $table) {
            $table->id();
			$table->string('type')->default('xeso');
			$table->string('from_year')->nullable();
			$table->string('to_year')->nullable();
			$table->string('from_date')->nullable();
			$table->string('to_date')->nullable();
			$table->string('price')->nullable();
			$table->string('price_type')->default('day');
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
        Schema::dropIfExists('pricing');
    }
}
