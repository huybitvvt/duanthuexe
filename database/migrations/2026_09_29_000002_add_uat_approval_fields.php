<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUatApprovalFields extends Migration
{
    public function up()
    {
        Schema::table('daily_cash_registers', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('daily_cash_registers', function (Blueprint $table) {
            $table->dropColumn(['submitted_by', 'submitted_at']);
        });
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'approved_by', 'approved_at']);
        });
    }
}
