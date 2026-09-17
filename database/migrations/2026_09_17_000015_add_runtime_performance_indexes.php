<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRuntimePerformanceIndexes extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at', 'orders_created_at_idx');
            $table->index('completed_at', 'orders_completed_at_idx');
            $table->index('customer_id', 'orders_customer_id_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('order_id', 'transactions_order_id_idx');
            $table->index(['created_at', 'type'], 'transactions_created_type_idx');
        });

        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $table->index('order_id', 'order_vehicle_details_order_id_idx');
            $table->index('vehicle_id', 'order_vehicle_details_vehicle_id_idx');
            $table->index('completed_at', 'order_vehicle_details_completed_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['order_id', 'created_at'], 'activity_logs_order_created_idx');
            $table->index('transaction_id', 'activity_logs_transaction_id_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index('order_id', 'leads_order_id_idx');
            $table->index('user_id', 'leads_user_id_idx');
            $table->index(['store_id', 'status', 'id'], 'leads_store_status_id_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['store_id', 'status'], 'vehicles_store_status_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['store_id', 'role_id'], 'users_store_role_idx');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_store_role_idx');
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_store_status_idx');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_store_status_id_idx');
            $table->dropIndex('leads_user_id_idx');
            $table->dropIndex('leads_order_id_idx');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_transaction_id_idx');
            $table->dropIndex('activity_logs_order_created_idx');
        });
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $table->dropIndex('order_vehicle_details_completed_idx');
            $table->dropIndex('order_vehicle_details_vehicle_id_idx');
            $table->dropIndex('order_vehicle_details_order_id_idx');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_created_type_idx');
            $table->dropIndex('transactions_order_id_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_id_idx');
            $table->dropIndex('orders_completed_at_idx');
            $table->dropIndex('orders_created_at_idx');
        });
    }
}
