<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReminderContactLogs extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reminder_contact_logs')) {
            Schema::create('reminder_contact_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('reminder_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('contact_date')->index();
                $table->text('note');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Contact histories are operational records; retain them on rollback.
    }
}
