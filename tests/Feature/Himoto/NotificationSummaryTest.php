<?php

namespace Tests\Feature\Himoto;

use App\Http\Controllers\NotificationController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class NotificationSummaryTest extends TestCase
{
    public function testOverdueOrdersLoadWithoutLegacyFlagColumn(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('next_time_manual')->nullable();
            $table->dateTime('next_time_auto')->nullable();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_status');
            $table->dateTime('return_at')->nullable();
            $table->string('contract_number')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('store_id')->nullable();
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('phone')->nullable();
        });
        Schema::create('vehicles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('license');
        });
        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('vehicle_id');
        });

        DB::table('orders')->insert([
            ['id' => 1, 'order_status' => 'renting', 'return_at' => Carbon::now()->subDay()],
            ['id' => 2, 'order_status' => 'renting', 'return_at' => Carbon::now()->addDay()],
        ]);
        DB::table('vehicles')->insert(['id' => 7, 'name' => 'Xe thử', 'license' => 'TEST-001']);
        DB::table('order_vehicle_details')->insert(['order_id' => 1, 'vehicle_id' => 7]);

        $response = app(NotificationController::class)->summary(Request::create('/api/auth/notifications/summary'));
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true)['data'];
        $this->assertSame(1, $data['orders']['count']);
        $this->assertSame('TEST-001', $data['orders']['items'][0]['vehicle_license']);
    }
}
