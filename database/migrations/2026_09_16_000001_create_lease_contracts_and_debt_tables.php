<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaseContractsAndDebtTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('lease_contracts')) {
            Schema::create('lease_contracts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('contract_code', 64)->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('deposit_amount', 15, 2)->default(0);
                $table->integer('installment_count')->default(12);
                $table->decimal('period_amount', 15, 2)->default(0);
                $table->string('status', 32)->default('active')->comment('active, completed, defaulted, cancelled');
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lease_installments')) {
            Schema::create('lease_installments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lease_contract_id')->index();
                $table->integer('period_number');
                $table->date('due_date')->index();
                $table->decimal('amount_due', 15, 2)->default(0);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->string('status', 32)->default('unpaid')->comment('unpaid, partially_paid, paid, overdue');
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lease_payment_allocations')) {
            Schema::create('lease_payment_allocations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lease_contract_id')->index();
                $table->unsignedBigInteger('installment_id')->nullable()->index();
                $table->unsignedBigInteger('transaction_id')->nullable()->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('payment_date');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('debt_notes')) {
            Schema::create('debt_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lease_contract_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->text('note_content');
                $table->date('appointment_date')->nullable()->index();
                $table->string('debt_classification', 32)->default('normal')->comment('normal, reminder, warning, bad_debt');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('debt_notes');
        Schema::dropIfExists('lease_payment_allocations');
        Schema::dropIfExists('lease_installments');
        Schema::dropIfExists('lease_contracts');
    }
}
