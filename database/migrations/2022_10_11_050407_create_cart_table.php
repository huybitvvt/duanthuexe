<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cart', function (Blueprint $table) {
            $table->id();
            $table->string('shop');
            $table->string('status');
            $table->string('customer');
            $table->string('name');
            $table->string('phone');
            $table->string('cccd');
            $table->string('address');
            $table->string('price_new');
            $table->string('price_old');
            $table->string('code');
            $table->string('id_xe');
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
        Schema::dropIfExists('cart');
    }
}
