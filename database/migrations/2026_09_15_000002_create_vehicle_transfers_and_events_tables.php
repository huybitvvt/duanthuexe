<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleTransfersAndEventsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('vehicle_transfers')) {
            Schema::create('vehicle_transfers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('transfer_code', 64)->unique();
                $table->string('type', 32)->default('store_to_store')->comment('store_to_store, return_different_store, vehicle_exchange');
                $table->unsignedBigInteger('from_store_id')->nullable()->index();
                $table->unsignedBigInteger('to_store_id')->nullable()->index();
                $table->string('status', 32)->default('dispatched')->comment('draft, dispatched, completed, cancelled');
                
                $table->unsignedBigInteger('dispatched_by')->nullable()->index();
                $table->timestamp('dispatched_at')->nullable();
                $table->unsignedBigInteger('received_by')->nullable()->index();
                $table->timestamp('received_at')->nullable();
                
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 128)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_transfer_items')) {
            Schema::create('vehicle_transfer_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('transfer_id')->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->string('source_status', 32)->nullable();
                $table->string('target_status', 32)->nullable();
                $table->integer('odometer_out')->nullable();
                $table->integer('odometer_in')->nullable();
                $table->text('condition_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_location_events')) {
            Schema::create('vehicle_location_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('from_store_id')->nullable()->index();
                $table->unsignedBigInteger('to_store_id')->nullable()->index();
                $table->string('event_type', 48)->comment('transfer_dispatch, transfer_receive, transfer_cancel, order_rent_out, order_return_same_store, order_return_different_store, vehicle_exchange');
                $table->string('ref_type', 32)->nullable()->comment('vehicle_transfers, orders, contract_amendments');
                $table->unsignedBigInteger('ref_id')->nullable()->index();
                $table->integer('odometer')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contract_amendments')) {
            Schema::create('contract_amendments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id')->index();
                $table->string('amendment_code', 64)->nullable()->unique();
                $table->string('amendment_type', 32)->default('vehicle_exchange')->comment('vehicle_exchange, price_adjustment, extension');
                $table->unsignedBigInteger('old_vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('new_vehicle_id')->nullable()->index();
                $table->timestamp('effective_at')->nullable();
                $table->decimal('price_difference', 15, 2)->default(0);
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
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
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('vehicle_location_events');
        Schema::dropIfExists('vehicle_transfer_items');
        Schema::dropIfExists('vehicle_transfers');
    }
}
