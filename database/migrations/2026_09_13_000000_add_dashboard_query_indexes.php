<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['store_id', 'created_at'], 'orders_store_created_idx');
                $table->index(['order_status', 'created_at'], 'orders_status_created_idx');
            });
        }
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['store_id', 'created_at'], 'transactions_store_created_idx');
                $table->index(['store_id', 'type', 'name'], 'transactions_store_type_name_idx');
            });
        }
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->index('status', 'vehicles_status_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ([['orders', 'orders_store_created_idx'], ['orders', 'orders_status_created_idx'], ['transactions', 'transactions_store_created_idx'], ['transactions', 'transactions_store_type_name_idx'], ['vehicles', 'vehicles_status_idx']] as [$table, $index]) {
            if (Schema::hasTable($table)) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
        }
    }
};
