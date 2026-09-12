<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrdersTableAddColumnCustomerId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable(true)->change();
            $table->string('customer_phone')->nullable(true)->change();
            $table->bigInteger('customer_id')->nullable();
            $table->string('note')->nullable()->comment('ghi chú hợp đồng');
            $table->string('note_payment')->nullable()->comment('ghi chú thanh toán');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
}
