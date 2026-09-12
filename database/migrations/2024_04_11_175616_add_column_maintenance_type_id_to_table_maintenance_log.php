<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnMaintenanceTypeIdToTableMaintenanceLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('maintenance_log', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('maintenance_log', function (Blueprint $table) {
            $table->dropColumn('maintenance_type_id');
        });
    }
}
