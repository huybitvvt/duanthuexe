<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSepayPaymentTables extends Migration
{
    public function up()
    {
        Schema::create('sepay_payment_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('bank_id');
            $table->string('bank_code', 10)->default('MB');
            $table->string('account_number', 30);
            $table->string('account_holder', 100);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('purpose', 30)->default('general');
            $table->unsignedBigInteger('line_item_id')->nullable();
            $table->dateTime('extension_return_at')->nullable();
            $table->dateTime('extension_from_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->bigInteger('expected_amount');
            $table->bigInteger('received_amount')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('note', 255);
            $table->timestamps();
            $table->index(['store_id', 'created_at']);
        });

        Schema::create('sepay_webhook_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sepay_transaction_id')->unique();
            $table->unsignedBigInteger('payment_request_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('excess_transaction_id')->nullable()->unique();
            $table->string('payment_code', 20)->nullable();
            $table->string('account_number', 30);
            $table->string('reference_code', 100)->nullable();
            $table->bigInteger('amount');
            $table->string('status', 20);
            $table->text('content')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sepay_webhook_events');
        Schema::dropIfExists('sepay_payment_requests');
    }
}
