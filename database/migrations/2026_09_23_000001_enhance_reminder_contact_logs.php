<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceReminderContactLogs extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reminder_contact_logs')) {
            Schema::table('reminder_contact_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('reminder_contact_logs', 'action')) {
                    $table->string('action', 50)->nullable()->after('note');
                }
                if (!Schema::hasColumn('reminder_contact_logs', 'paid_amount')) {
                    $table->decimal('paid_amount', 14, 2)->nullable()->default(0)->after('action');
                }
                if (!Schema::hasColumn('reminder_contact_logs', 'appointment_date')) {
                    $table->date('appointment_date')->nullable()->after('paid_amount');
                }
            });
        }
    }

    public function down(): void
    {
    }
}
