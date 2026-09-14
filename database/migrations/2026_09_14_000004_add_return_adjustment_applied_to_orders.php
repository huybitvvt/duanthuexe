<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReturnAdjustmentAppliedToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // NULL means a legacy settlement; never confuse a preview with an applied adjustment.
            $table->decimal('return_adjustment_applied', 18, 0)->nullable();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('return_adjustment_applied');
        });
    }
}
