<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContractFieldsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'contract_number')) {
                $table->string('contract_number', 32)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('orders', 'contract_issued_at')) {
                $table->timestamp('contract_issued_at')->nullable()->after('contract_number');
            }
            if (!Schema::hasColumn('orders', 'contract_signed_on')) {
                $table->date('contract_signed_on')->nullable()->after('contract_issued_at');
            }
            if (!Schema::hasColumn('orders', 'contract_responsible_user_id')) {
                $table->unsignedBigInteger('contract_responsible_user_id')->nullable()->after('contract_signed_on');
            }
            if (!Schema::hasColumn('orders', 'contract_authorization_date')) {
                $table->date('contract_authorization_date')->nullable()->after('contract_responsible_user_id');
            }
            if (!Schema::hasColumn('orders', 'contract_authorization_party_name')) {
                $table->string('contract_authorization_party_name', 255)->nullable()->after('contract_authorization_date');
            }
            if (!Schema::hasColumn('orders', 'contract_collateral_description')) {
                $table->text('contract_collateral_description')->nullable()->after('contract_authorization_party_name');
            }
            if (!Schema::hasColumn('orders', 'contract_signer_a_name')) {
                $table->string('contract_signer_a_name', 255)->nullable()->after('contract_collateral_description');
            }
            if (!Schema::hasColumn('orders', 'contract_signer_b_name')) {
                $table->string('contract_signer_b_name', 255)->nullable()->after('contract_signer_a_name');
            }
            if (!Schema::hasColumn('orders', 'contract_snapshot')) {
                $table->jsonb('contract_snapshot')->nullable()->after('contract_signer_b_name');
            }
            if (!Schema::hasColumn('orders', 'return_signer_a_name')) {
                $table->string('return_signer_a_name', 255)->nullable()->after('contract_snapshot');
            }
            if (!Schema::hasColumn('orders', 'return_signer_b_name')) {
                $table->string('return_signer_b_name', 255)->nullable()->after('return_signer_a_name');
            }
            if (!Schema::hasColumn('orders', 'return_additional_note')) {
                $table->text('return_additional_note')->nullable()->after('return_signer_b_name');
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
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'contract_number',
                'contract_issued_at',
                'contract_signed_on',
                'contract_responsible_user_id',
                'contract_authorization_date',
                'contract_authorization_party_name',
                'contract_collateral_description',
                'contract_signer_a_name',
                'contract_signer_b_name',
                'contract_snapshot',
                'return_signer_a_name',
                'return_signer_b_name',
                'return_additional_note',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
