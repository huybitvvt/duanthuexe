<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDepositAndRentalColumnsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->double('first_deposit_amount', 18)->nullable();
            $table->string('first_deposit_payment_method')->nullable();
            
			$table->double('total_rental_fees', 18)->nullable();
            $table->string('total_rental_payment_method')->nullable();
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
            $table->dropColumn('first_deposit_amount');
            $table->dropColumn('first_deposit_payment_method');
			
            $table->dropColumn('total_rental_fees');
            $table->dropColumn('total_rental_payment_method');
        });
    }
}
