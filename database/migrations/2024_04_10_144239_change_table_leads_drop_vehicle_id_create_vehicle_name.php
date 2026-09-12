<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTableLeadsDropVehicleIdCreateVehicleName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
         
            $table->dropColumn('vehicle_id');
           
            $table->string('vehicle_name')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            
            $table->unsignedBigInteger('vehicle_id')->after('id');
          
            $table->dropColumn('vehicle_name');
        });
    }
}
