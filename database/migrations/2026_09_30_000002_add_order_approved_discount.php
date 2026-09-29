<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderApprovedDiscount extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('orders', 'approved_discount_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('approved_discount_amount')->default(0);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('orders', 'approved_discount_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('approved_discount_amount');
            });
        }
    }
}
