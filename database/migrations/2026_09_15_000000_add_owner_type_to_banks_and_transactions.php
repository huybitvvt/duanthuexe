<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOwnerTypeToBanksAndTransactions extends Migration
{
    public function up()
    {
        if (Schema::hasTable('banks') && !Schema::hasColumn('banks', 'owner_type')) {
            Schema::table('banks', function (Blueprint $table) {
                // 'personal', 'company', 'unknown'. Dữ liệu cũ mặc định là 'unknown' để tránh suy đoán.
                $table->string('owner_type', 32)->default('unknown')->after('account_type');
            });
        }

        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'bank_owner_type')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Lưu snapshot loại chủ tài khoản tại thời điểm thu chuyển khoản
                $table->string('bank_owner_type', 32)->nullable()->after('bank_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('banks') && Schema::hasColumn('banks', 'owner_type')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->dropColumn('owner_type');
            });
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'bank_owner_type')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('bank_owner_type');
            });
        }
    }
}
