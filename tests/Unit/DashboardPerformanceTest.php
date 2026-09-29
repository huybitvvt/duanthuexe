<?php

namespace Tests\Unit;

use App\Http\Services\DashboardService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    private $admin;
    private $staff;
    private $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $this->now = Carbon::parse('2026-09-17 12:00:00', 'Asia/Ho_Chi_Minh');
        Carbon::setTestNow($this->now);
        Cache::flush();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret123'),
            'role_id' => 1,
            'status' => 1,
        ]);
        $this->staff = User::create([
            'name' => 'Store staff',
            'email' => 'staff@example.test',
            'password' => bcrypt('secret123'),
            'role_id' => 2,
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->seedMetrics();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testOverviewUsesTwoQueriesAndReturnsAllMetrics(): void
    {
        auth()->login($this->admin);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $overview = app(DashboardService::class)->overview();

        $this->assertSame(2, $queryCount);
        $report = $overview['report'];
        $this->assertSame(4, $report['total_vehicle']);
        $this->assertSame(1, $report['total_vehicle_using']);
        $this->assertSame(1, $report['total_vehicle_ready']);
        $this->assertSame(1, $report['total_vehicle_repairing']);
        $this->assertSame(1, $report['total_vehicle_broken']);
        $this->assertSame(3, $report['total_customer']);
        $this->assertSame(1, $report['total_staff']);
        $this->assertSame(2, $report['total_order_in_day']);
        $this->assertSame(3, $report['total_order_in_month']);
        $this->assertSame(1, $report['total_order_out_date_in_month']);
        $this->assertSame(1700.0, $report['total_deposit_in_day_new']);
        $this->assertSame(2200.0, $report['total_deposit_in_month_new']);
        $this->assertSame(200.0, $report['total_rental_fees_in_day_new']);
        $this->assertSame(50.0, $report['total_renew_in_day_new']);
        $this->assertSame(300.0, $report['total_refund_in_month_new']);
        $this->assertSame(700.0, $report['total_origin_refund_in_day_new']);
        $this->assertSame(1300.0, $report['total_origin_refund_in_month_new']);
        $this->assertSame(-25.0, $report['total_money_early_in_month_new']);
        $this->assertSame(40.0, $report['total_money_out_date_in_day_new']);
        $this->assertCount(17, $overview['chart']['labels']);
    }

    public function testDraftIntakeDoesNotIncreaseContractCounts(): void
    {
        auth()->login($this->admin);
        DB::table('orders')->insert([
            'store_id' => 1,
            'order_status' => 'draft',
            'created_at' => $this->now,
        ]);
        Cache::flush();

        $report = app(DashboardService::class)->report();
        $this->assertSame(2, $report['total_order_in_day']);
        $this->assertSame(3, $report['total_order_in_month']);
    }

    public function testReportScopesToStaffStoreAndUsesSharedCache(): void
    {
        auth()->login($this->staff);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $service = app(DashboardService::class);
        $first = $service->report();
        $second = $service->report();

        $this->assertSame(1, $queryCount);
        $this->assertSame($first, $second);
        $this->assertSame(3, $first['total_vehicle']);
        $this->assertSame(2, $first['total_customer']);
        $this->assertSame(1, $first['total_staff']);
        $this->assertSame(1000.0, $first['total_deposit_in_day_new']);
        $this->assertSame(1500.0, $first['total_deposit_in_month_new']);
        $this->assertSame(0.0, $first['total_money_out_date_in_day_new']);
        $this->assertSame(-25.0, $first['total_money_early_in_month_new']);
    }

    private function seedMetrics(): void
    {
        $monthDate = $this->now->copy()->subDays(5);

        DB::table('vehicles')->insert([
            ['store_id' => 1, 'status' => 'using'],
            ['store_id' => 1, 'status' => 'ready'],
            ['store_id' => 1, 'status' => 'broken'],
            ['store_id' => 2, 'status' => 'repairing'],
        ]);
        DB::table('customers')->insert([
            ['id' => 1, 'name' => 'Customer 1'],
            ['id' => 2, 'name' => 'Customer 2'],
            ['id' => 3, 'name' => 'Customer 3'],
        ]);
        DB::table('orders')->insert([
            [
                'id' => 1,
                'customer_id' => 1,
                'store_id' => 1,
                'order_status' => 'renting',
                'out_dated_at' => 10,
                'total' => '100',
                'first_deposit_amount' => 0,
                'additional_deposit_amount' => 0,
                'completed_at' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ],
            [
                'id' => 2,
                'customer_id' => 2,
                'store_id' => 1,
                'order_status' => 'completed',
                'out_dated_at' => 0,
                'total' => '200',
                'first_deposit_amount' => 500,
                'additional_deposit_amount' => 100,
                'completed_at' => $monthDate,
                'created_at' => $monthDate,
                'updated_at' => $monthDate,
            ],
            [
                'id' => 3,
                'customer_id' => 3,
                'store_id' => 2,
                'order_status' => 'completed',
                'out_dated_at' => 0,
                'total' => '300',
                'first_deposit_amount' => 700,
                'additional_deposit_amount' => 0,
                'completed_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ],
        ]);
        DB::table('transactions')->insert([
            ['order_id' => 1, 'store_id' => 1, 'name' => 'order:deposit:1', 'type' => 'in', 'value' => 1000, 'created_at' => $this->now, 'updated_at' => $this->now],
            ['order_id' => 1, 'store_id' => 1, 'name' => 'order:rental_fees', 'type' => 'in', 'value' => 200, 'created_at' => $this->now, 'updated_at' => $this->now],
            ['order_id' => 1, 'store_id' => 1, 'name' => 'addon', 'type' => 'addon', 'value' => 50, 'created_at' => $this->now, 'updated_at' => $this->now],
            ['order_id' => 2, 'store_id' => 1, 'name' => 'order:deposit:2', 'type' => 'in', 'value' => 500, 'created_at' => $monthDate, 'updated_at' => $monthDate],
            ['order_id' => 2, 'store_id' => 1, 'name' => 'order:complete:2', 'type' => 'out', 'value' => 300, 'created_at' => $monthDate, 'updated_at' => $monthDate],
            ['order_id' => 3, 'store_id' => 2, 'name' => 'order:deposit:3', 'type' => 'in', 'value' => 700, 'created_at' => $this->now, 'updated_at' => $this->now],
        ]);
        DB::table('order_vehicle_details')->insert([
            ['order_id' => 2, 'handler_price' => 0, 'money_out_date' => -25, 'completed_at' => $monthDate, 'created_at' => $monthDate, 'updated_at' => $monthDate],
            ['order_id' => 3, 'handler_price' => 0, 'money_out_date' => 40, 'completed_at' => $this->now, 'created_at' => $this->now, 'updated_at' => $this->now],
        ]);
    }

    private function createTables(): void
    {
        foreach (['order_vehicle_details', 'transactions', 'orders', 'vehicles', 'customers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('role_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_status')->nullable();
            $table->integer('out_dated_at')->default(0);
            $table->string('total')->nullable();
            $table->decimal('first_deposit_amount', 15, 2)->default(0);
            $table->decimal('additional_deposit_amount', 15, 2)->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->decimal('handler_price', 15, 2)->default(0);
            $table->decimal('money_out_date', 15, 2)->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
