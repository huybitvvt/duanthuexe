<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrStaffAndSchedulesTables extends Migration
{
    public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('staff_code', 50)->unique();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('id_card', 30)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('position', 100)->nullable();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('status', 20)->default('active'); // active, leave, terminated
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('store_duty_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id')->index();
            $table->date('duty_date')->index();
            $table->string('shift_name', 50)->default('Cả ngày'); // Ca sáng, Ca chiều, Cả ngày
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('staff_name', 150);
            $table->string('staff_phone', 30)->nullable();
            $table->string('role_in_shift', 100)->default('Nhân viên trực'); // Trưởng ca, Nhân viên trực, Kỹ thuật
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'duty_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('store_duty_schedules');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('departments');
    }
}
