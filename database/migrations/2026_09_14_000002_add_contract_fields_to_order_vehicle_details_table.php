<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContractFieldsToOrderVehicleDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_vehicle_details', 'driver_name')) {
                $table->string('driver_name', 255)->nullable()->after('borrow_hats');
            }
            if (!Schema::hasColumn('order_vehicle_details', 'driver_license_number')) {
                $table->string('driver_license_number', 64)->nullable()->after('driver_name');
            }
            if (!Schema::hasColumn('order_vehicle_details', 'driver_license_issued_on')) {
                $table->date('driver_license_issued_on')->nullable()->after('driver_license_number');
            }
            if (!Schema::hasColumn('order_vehicle_details', 'borrow_raincoats')) {
                $table->integer('borrow_raincoats')->default(0)->after('driver_license_issued_on');
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
        Schema::table('order_vehicle_details', function (Blueprint $table) {
            $columns = [
                'driver_name',
                'driver_license_number',
                'driver_license_issued_on',
                'borrow_raincoats',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_vehicle_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
