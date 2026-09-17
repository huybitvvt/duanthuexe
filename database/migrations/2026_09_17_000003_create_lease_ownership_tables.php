<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLeaseOwnershipTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('lease_ownership_requests')) {
            Schema::create('lease_ownership_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lease_contract_id')->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('store_id')->index();
                $table->string('status', 32)->default('draft')->index();
                
                // Snapshot values
                $table->decimal('total_contract_amount', 15, 2);
                $table->decimal('total_paid_amount', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('remaining_debt', 15, 2)->default(0);
                $table->integer('unpaid_installments_count')->default(0);
                
                $table->longText('checklist_documents')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_reason')->nullable();
                
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->unsignedBigInteger('executed_by')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->text('execution_notes')->nullable();
                
                $table->string('idempotency_key', 100)->unique()->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lease_ownership_events')) {
            Schema::create('lease_ownership_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ownership_request_id')->index();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('vehicle_ownerships')) {
            Schema::create('vehicle_ownerships', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('lease_contract_id')->nullable()->index();
                $table->unsignedBigInteger('ownership_request_id')->nullable()->index();
                $table->timestamp('transferred_at');
                $table->string('certificate_number', 100)->nullable();
                $table->text('notes')->nullable();
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
        Schema::dropIfExists('vehicle_ownerships');
        Schema::dropIfExists('lease_ownership_events');
        Schema::dropIfExists('lease_ownership_requests');
    }
}
