<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessApprovalRequests extends Migration
{
    public function up()
    {
        if (Schema::hasTable('business_approval_requests')) {
            return;
        }
        Schema::create('business_approval_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('store_id');
            $table->string('action', 40);
            $table->string('status', 24)->default('submitted');
            $table->text('reason');
            $table->text('payload')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'action', 'status'], 'business_approval_subject_idx');
            $table->index(['store_id', 'status'], 'business_approval_store_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_approval_requests');
    }
}
