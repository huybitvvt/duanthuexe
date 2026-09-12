<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdditionalDepositToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->double('additional_deposit_amount', 18)->nullable()->after('first_deposit_payment_method');
            $table->string('additional_deposit_payment_method')->nullable()->after('additional_deposit_amount');
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
            $table->dropColumn('additional_deposit_amount');
            $table->dropColumn('additional_deposit_payment_method');
        });
    }
}
