<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReversalFieldsToLeasePaymentAllocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('lease_payment_allocations')) {
            Schema::table('lease_payment_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('lease_payment_allocations', 'status')) {
                    $table->string('status', 32)->default('active')->index()->comment('active, reversed');
                }
                if (!Schema::hasColumn('lease_payment_allocations', 'reversal_transaction_id')) {
                    $table->unsignedBigInteger('reversal_transaction_id')->nullable()->index();
                }
                if (!Schema::hasColumn('lease_payment_allocations', 'reversal_reason')) {
                    $table->text('reversal_reason')->nullable();
                }
                if (!Schema::hasColumn('lease_payment_allocations', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable();
                }
                if (!Schema::hasColumn('lease_payment_allocations', 'reversed_by')) {
                    $table->unsignedBigInteger('reversed_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('lease_contracts')) {
            Schema::table('lease_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('lease_contracts', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('lease_contracts', 'settled_at')) {
                    $table->timestamp('settled_at')->nullable();
                }
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
        if (Schema::hasTable('lease_payment_allocations')) {
            Schema::table('lease_payment_allocations', function (Blueprint $table) {
                $columns = ['status', 'reversal_transaction_id', 'reversal_reason', 'reversed_at', 'reversed_by'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('lease_payment_allocations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('lease_contracts')) {
            Schema::table('lease_contracts', function (Blueprint $table) {
                $columns = ['discount_amount', 'settled_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('lease_contracts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
