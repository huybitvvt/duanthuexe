<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContractFieldsToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'id_card_issued_on')) {
                $table->date('id_card_issued_on')->nullable()->after('id_card');
            }
            if (!Schema::hasColumn('customers', 'id_card_issued_by')) {
                $table->string('id_card_issued_by', 255)->nullable()->after('id_card_issued_on');
            }
            if (!Schema::hasColumn('customers', 'relatives')) {
                $table->jsonb('relatives')->nullable()->after('id_card_issued_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = [
                'id_card_issued_on',
                'id_card_issued_by',
                'relatives',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
