<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAuditEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('audit_events')) {
            Schema::create('audit_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('subject_type', 100)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->longText('before_json')->nullable();
                $table->longText('after_json')->nullable();
                $table->text('reason')->nullable();
                $table->string('request_id', 100)->nullable()->index();
                $table->string('ip_hash', 64)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['subject_type', 'subject_id']);
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
        Schema::dropIfExists('audit_events');
    }
}
