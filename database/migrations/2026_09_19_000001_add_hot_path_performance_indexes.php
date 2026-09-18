<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHotPathPerformanceIndexes extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['store_id', 'order_status', 'id'], 'orders_store_status_id_idx');
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->index(['current_store_id', 'status', 'id'], 'vehicles_current_store_status_id_idx');
            });
        }

        if (Schema::hasTable('contract_amendments')) {
            Schema::table('contract_amendments', function (Blueprint $table) {
                $table->index(['order_id', 'amendment_type'], 'contract_amendments_order_type_idx');
            });
        }

        if (Schema::hasTable('order_vehicle_details')) {
            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->index(['rent_at', 'order_id'], 'order_vehicle_details_rent_order_idx');
                $table->index(['return_at', 'order_id'], 'order_vehicle_details_return_order_idx');
            });
        }

        if (Schema::hasTable('vehicle_transfers')) {
            Schema::table('vehicle_transfers', function (Blueprint $table) {
                $table->index(['status', 'from_store_id'], 'vehicle_transfers_status_from_idx');
                $table->index(['status', 'to_store_id'], 'vehicle_transfers_status_to_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('vehicle_transfers')) {
            Schema::table('vehicle_transfers', function (Blueprint $table) {
                $table->dropIndex('vehicle_transfers_status_to_idx');
                $table->dropIndex('vehicle_transfers_status_from_idx');
            });
        }
        if (Schema::hasTable('order_vehicle_details')) {
            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->dropIndex('order_vehicle_details_return_order_idx');
                $table->dropIndex('order_vehicle_details_rent_order_idx');
            });
        }
        if (Schema::hasTable('contract_amendments')) {
            Schema::table('contract_amendments', function (Blueprint $table) {
                $table->dropIndex('contract_amendments_order_type_idx');
            });
        }
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropIndex('vehicles_current_store_status_id_idx');
            });
        }
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_store_status_id_idx');
            });
        }
    }
}
