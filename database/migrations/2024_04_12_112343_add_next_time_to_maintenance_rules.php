<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNextTimeToMaintenanceRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('maintenance_rules', function (Blueprint $table) {
            $table->datetime('next_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void 
     */
    public function down()
    {
        Schema::table('maintenance_rules', function (Blueprint $table) {
            $table->dropColumn('next_time');
        });
    }
}
