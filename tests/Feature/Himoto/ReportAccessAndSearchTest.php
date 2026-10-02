<?php

namespace Tests\Feature\Himoto;

use App\Http\Services\ReportService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportAccessAndSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('roles', function ($t) { $t->increments('id'); $t->string('slug'); });
        Schema::create('stores', function ($t) { $t->increments('id'); $t->string('store_name'); });
        Schema::create('customers', function ($t) { $t->increments('id'); $t->string('name'); $t->string('phone'); });
        Schema::create('vehicles', function ($t) { $t->increments('id'); $t->string('name'); $t->string('license'); });
        Schema::create('orders', function ($t) {
            $t->increments('id'); $t->integer('store_id'); $t->integer('customer_id'); $t->string('contract_number');
            $t->string('order_status'); $t->integer('out_dated_at')->default(0); $t->integer('first_deposit_amount')->default(0);
            $t->integer('additional_deposit_amount')->default(0); $t->timestamp('completed_at')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('order_vehicle_details', function ($t) {
            $t->increments('id'); $t->integer('vehicle_id'); $t->integer('order_id'); $t->timestamp('rent_at');
            $t->timestamp('completed_at'); $t->integer('handler_price')->default(0); $t->integer('total_money')->default(100);
            $t->integer('money_out_date')->default(0); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('transactions', function ($t) {
            $t->increments('id'); $t->integer('order_id'); $t->integer('store_id'); $t->integer('user_id');
            $t->string('type'); $t->string('name'); $t->integer('value'); $t->timestamps();
        });
        DB::table('roles')->insert([['id' => 5, 'slug' => 'ke-toan'], ['id' => 6, 'slug' => 'ban-giam-doc'], ['id' => 7, 'slug' => 'quan-ly-cua-hang']]);
        DB::table('stores')->insert([['id' => 31, 'store_name' => 'CS1'], ['id' => 32, 'store_name' => 'CS2']]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Fixture customer', 'phone' => '0901234567']);
        DB::table('vehicles')->insert(['id' => 1, 'name' => 'Honda fixture', 'license' => 'QA-001']);
        foreach ([31, 32] as $i => $store) {
            DB::table('orders')->insert(['id' => $i + 1, 'store_id' => $store, 'customer_id' => 1, 'contract_number' => 'QA-'.($i + 1),
                'order_status' => 'completed', 'created_at' => '2026-10-01 08:00:00', 'completed_at' => '2026-10-02 08:00:00']);
            DB::table('order_vehicle_details')->insert(['order_id' => $i + 1, 'vehicle_id' => 1, 'rent_at' => '2026-10-01 08:00:00', 'completed_at' => '2026-10-02 08:00:00']);
            DB::table('transactions')->insert(['order_id' => $i + 1, 'store_id' => $store, 'user_id' => 1, 'name' => 'order:deposit',
                'type' => 'in', 'value' => 100 + $i, 'created_at' => '2026-10-01 08:00:00']);
        }
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
    }

    public function testCompanyReportsAllowAccountantAndDirectorWithoutAssignedStore(): void
    {
        foreach ([5, 6] as $roleId) {
            $this->actingAs(new User(['id' => 1, 'role_id' => $roleId, 'status' => 'active']), 'api');
            $this->assertCount(2, app(ReportService::class)->getOrderItems([]));
            $this->assertCount(1, app(ReportService::class)->getOrderItems(['store_id' => 31]));
            $this->assertCount(2, app(ReportService::class)->getTransactions([]));
            $this->getJson('/api/auth/report/detail-report?start_date=2026-10-01&end_date=2026-10-02')->assertStatus(200);
        }
    }

    public function testQuickCountersRespectCompanyAndBranchCapabilities(): void
    {
        $this->actingAs(new User(['id' => 1, 'role_id' => 6]), 'api');
        $this->assertSame(2, app(ReportService::class)->quickOrderStats([])['total_order']);
        $this->actingAs(new User(['id' => 2, 'role_id' => 7, 'store_id' => 31]), 'api');
        $this->assertSame(1, app(ReportService::class)->quickOrderStats([])['total_order']);
        $this->assertCount(1, app(ReportService::class)->getOrderItems(['store_id' => 32]));
    }

    public function testTextNumberAndContractSearchProduceCorrectTotals(): void
    {
        $this->actingAs(new User(['id' => 1, 'role_id' => 1, 'status' => 'active']), 'api');
        $service = app(ReportService::class);
        foreach (['Honda', 'Fixture customer', 'QA-001'] as $keyword) {
            $this->assertSame(201.0, $service->handleDetailReportNew(['keyword' => $keyword])['total_deposit']);
            $this->assertEquals(201, $service->detailReportDayByDay(['keyword' => $keyword])['total_deposit']->sum('total_value'));
        }
        $this->assertSame(201.0, $service->handleDetailReportNew(['keyword' => '1'])['total_deposit']);
        foreach (['#1', 'QA-1'] as $keyword) {
            $this->assertSame(100.0, $service->handleDetailReportNew(['keyword' => $keyword])['total_deposit']);
        }
        $this->assertSame(0.0, $service->handleDetailReportNew(['keyword' => 'No matching record'])['total_deposit']);
    }
}
