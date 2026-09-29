<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGuardianToLeaseContracts extends Migration
{
    public function up()
    {
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->string('guardian_name', 191)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('guardian_id_card', 32)->nullable();
        });
    }

    public function down()
    {
        Schema::table('lease_contracts', function (Blueprint $table) {
            $table->dropColumn(['guardian_name', 'guardian_phone', 'guardian_id_card']);
        });
    }
}
