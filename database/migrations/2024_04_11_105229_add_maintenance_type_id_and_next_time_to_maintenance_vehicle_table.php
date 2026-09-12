<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMaintenanceTypeIdAndNextTimeToMaintenanceVehicleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       
        Schema::table('maintenance_vehicle', function (Blueprint $table) {
           
            $table->unsignedBigInteger('maintenance_type_id')->after('id')->change();

          
            $table->timestamp('next_time')->after('last_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('maintenance_vehicle', function (Blueprint $table) {
            $table->dropColumn('next_time');
        });
    }
}
