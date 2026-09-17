<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EnhanceCustomerReminderOutboxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('customer_reminder_outbox')) {
            Schema::table('customer_reminder_outbox', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_reminder_outbox', 'provider')) {
                    $table->string('provider', 50)->nullable()->after('channel');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'provider_message_id')) {
                    $table->string('provider_message_id', 100)->nullable()->index()->after('provider');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'template_code')) {
                    $table->string('template_code', 50)->nullable()->after('stage');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'attempted_at')) {
                    $table->timestamp('attempted_at')->nullable()->after('scheduled_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'next_attempt_at')) {
                    $table->timestamp('next_attempt_at')->nullable()->index()->after('attempted_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('sent_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable()->after('delivered_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable()->after('failed_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'locked_by')) {
                    $table->string('locked_by', 100)->nullable()->after('locked_at');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'last_http_status')) {
                    $table->integer('last_http_status')->nullable()->after('locked_by');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'consent_source')) {
                    $table->string('consent_source', 100)->nullable()->after('last_http_status');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'consent_captured_at')) {
                    $table->timestamp('consent_captured_at')->nullable()->after('consent_source');
                }
                if (!Schema::hasColumn('customer_reminder_outbox', 'cancel_reason')) {
                    $table->string('cancel_reason', 255)->nullable()->after('error_message');
                }
            });
        }

        if (!Schema::hasTable('reminder_delivery_events')) {
            Schema::create('reminder_delivery_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('outbox_id')->index();
                $table->string('provider', 50)->nullable();
                $table->string('provider_message_id', 100)->nullable()->index();
                $table->string('event_type', 50)->index(); // dispatched, accepted, delivered, failed, rejected
                $table->integer('http_status')->nullable();
                $table->longText('payload_json')->nullable();
                $table->timestamp('created_at')->useCurrent();
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
        if (Schema::hasTable('customer_reminder_outbox')) {
            $addedColumns = [
                'provider', 'provider_message_id', 'template_code', 'attempted_at',
                'next_attempt_at', 'delivered_at', 'failed_at', 'locked_at',
                'locked_by', 'last_http_status', 'consent_source',
                'consent_captured_at', 'cancel_reason',
            ];
            $columns = array_values(array_filter($addedColumns, function ($column) {
                return Schema::hasColumn('customer_reminder_outbox', $column);
            }));
            if ($columns) {
                Schema::table('customer_reminder_outbox', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('reminder_delivery_events');
    }
}
