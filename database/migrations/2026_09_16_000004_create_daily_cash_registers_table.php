<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyCashRegistersTable extends Migration
{
    public function up()
    {
        Schema::create('daily_cash_registers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->date('register_date')->index();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->integer('total_orders_count')->default(0);

            // Cash breakdown
            $table->decimal('deposit_cash', 15, 2)->default(0);
            $table->decimal('rental_cash', 15, 2)->default(0);
            $table->decimal('renewal_cash', 15, 2)->default(0);
            $table->decimal('refund_deposit_cash', 15, 2)->default(0);
            $table->decimal('penalty_cash', 15, 2)->default(0);
            $table->decimal('other_income_cash', 15, 2)->default(0);
            $table->decimal('other_expense_cash', 15, 2)->default(0);

            // Bank Personal breakdown
            $table->decimal('deposit_bank_personal', 15, 2)->default(0);
            $table->decimal('rental_bank_personal', 15, 2)->default(0);
            $table->decimal('renewal_bank_personal', 15, 2)->default(0);
            $table->decimal('refund_deposit_bank_personal', 15, 2)->default(0);
            $table->decimal('penalty_bank_personal', 15, 2)->default(0);

            // Bank Company breakdown
            $table->decimal('deposit_bank_company', 15, 2)->default(0);
            $table->decimal('rental_bank_company', 15, 2)->default(0);
            $table->decimal('renewal_bank_company', 15, 2)->default(0);
            $table->decimal('refund_deposit_bank_company', 15, 2)->default(0);
            $table->decimal('penalty_bank_company', 15, 2)->default(0);

            // Balances & Closing
            $table->decimal('system_cash_balance', 15, 2)->default(0);
            $table->decimal('actual_cash_counted', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->default(0);
            $table->text('difference_reason')->nullable();

            $table->string('status', 20)->default('open'); // open, closed
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'register_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_cash_registers');
    }
}
