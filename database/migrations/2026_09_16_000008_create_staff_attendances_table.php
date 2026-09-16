<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffAttendancesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('staff_attendances')) {
            return;
        }

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->date('attendance_date')->index();
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->unsignedInteger('work_minutes')->default(0);
            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'attendance_date'], 'staff_attendance_unique_day');
            $table->index(['store_id', 'attendance_date'], 'staff_attendance_store_day');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_attendances');
    }
}
