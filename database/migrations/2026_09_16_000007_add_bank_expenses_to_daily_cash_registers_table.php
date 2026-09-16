<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankExpensesToDailyCashRegistersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('daily_cash_registers')) {
            Schema::table('daily_cash_registers', function (Blueprint $table) {
                if (!Schema::hasColumn('daily_cash_registers', 'other_expense_bank_personal')) {
                    $table->decimal('other_expense_bank_personal', 15, 2)->default(0)->after('penalty_bank_personal');
                }
                if (!Schema::hasColumn('daily_cash_registers', 'other_income_bank_personal')) {
                    $table->decimal('other_income_bank_personal', 15, 2)->default(0)->after('other_expense_bank_personal');
                }
                if (!Schema::hasColumn('daily_cash_registers', 'other_expense_bank_company')) {
                    $table->decimal('other_expense_bank_company', 15, 2)->default(0)->after('penalty_bank_company');
                }
                if (!Schema::hasColumn('daily_cash_registers', 'other_income_bank_company')) {
                    $table->decimal('other_income_bank_company', 15, 2)->default(0)->after('other_expense_bank_company');
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
        if (Schema::hasTable('daily_cash_registers')) {
            Schema::table('daily_cash_registers', function (Blueprint $table) {
                $columns = [
                    'other_expense_bank_personal',
                    'other_income_bank_personal',
                    'other_expense_bank_company',
                    'other_income_bank_company',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('daily_cash_registers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
