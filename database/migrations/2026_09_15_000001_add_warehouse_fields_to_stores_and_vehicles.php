<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarehouseFieldsToStoresAndVehicles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (!Schema::hasColumn('stores', 'kind')) {
                    $table->string('kind')->default('physical')->comment('physical, lease_to_own');
                }
                if (!Schema::hasColumn('stores', 'code')) {
                    $table->string('code')->nullable()->unique();
                }
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'current_store_id')) {
                    $table->unsignedBigInteger('current_store_id')->nullable()->index()->comment('Actual physical location of vehicle');
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
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (Schema::hasColumn('vehicles', 'current_store_id')) {
                    $table->dropColumn('current_store_id');
                }
            });
        }

        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (Schema::hasColumn('stores', 'code')) {
                    $table->dropColumn('code');
                }
                if (Schema::hasColumn('stores', 'kind')) {
                    $table->dropColumn('kind');
                }
            });
        }
    }
}
