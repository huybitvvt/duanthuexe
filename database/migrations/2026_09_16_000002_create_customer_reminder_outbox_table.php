<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerReminderOutboxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_reminder_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('contract_type', 30); // 'rental', 'lease'
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('installment_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('channel', 30)->default('call_task'); // 'call_task', 'sms', 'zalo'
            $table->string('stage', 50); // 'due_soon_3d', 'due_today', 'overdue_1d', 'overdue_7d', etc.
            $table->string('recipient_phone', 30);
            $table->string('recipient_name', 191)->nullable();
            $table->text('message_content');
            $table->string('status', 30)->default('pending'); // 'pending', 'sent', 'failed', 'cancelled', 'skipped'
            $table->dateTime('scheduled_at');
            $table->dateTime('sent_at')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->text('provider_response')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['contract_type', 'contract_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_reminder_outbox');
    }
}
