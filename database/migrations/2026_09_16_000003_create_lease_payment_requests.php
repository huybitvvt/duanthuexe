<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeasePaymentRequests extends Migration
{
    public function up()
    {
        Schema::create('lease_payment_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lease_contract_id');
            $table->string('request_key', 100);
            $table->string('fingerprint', 64);
            $table->text('result');
            $table->timestamps();
            $table->unique(['lease_contract_id', 'request_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lease_payment_requests');
    }
}
