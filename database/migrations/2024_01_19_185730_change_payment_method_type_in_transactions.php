<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePaymentMethodTypeInTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

      
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('payment_method')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

       
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bank'])->change();
        });
    }
}
