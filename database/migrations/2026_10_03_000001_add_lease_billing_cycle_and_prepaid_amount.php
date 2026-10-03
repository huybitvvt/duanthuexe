<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLeaseBillingCycleAndPrepaidAmount extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lease_contracts')) {
            return;
        }
        Schema::table('lease_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('lease_contracts', 'billing_cycle')) {
                $table->string('billing_cycle', 16)->default('month');
            }
            if (!Schema::hasColumn('lease_contracts', 'prepaid_amount')) {
                $table->decimal('prepaid_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('lease_contracts', 'paperwork')) {
                $table->text('paperwork')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('lease_contracts')) {
            return;
        }
        Schema::table('lease_contracts', function (Blueprint $table) {
            foreach (['billing_cycle', 'prepaid_amount', 'paperwork'] as $column) {
                if (Schema::hasColumn('lease_contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
