<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCollumnSaleIdInSellOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sell_orders', function (Blueprint $table) {
            $table->integer('sale_id')->default(0)->comment('Nhân viên phụ trách thuê,bán');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sell_orders', function (Blueprint $table) {
            $table->dropColumn('sale_id');
        });
    }
}
