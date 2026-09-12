<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeDataTypeOfOrderFeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_fees', function (Blueprint $table) {
            $table->dropColumn('order_id');
            $table->dropColumn('name');
            
        });

        Schema::table('order_fees', function (Blueprint $table) {
          
          
            $table->bigInteger('order_id');
            $table->string('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table->bigInteger('order_id');
        $table->string('name');
    }
}
