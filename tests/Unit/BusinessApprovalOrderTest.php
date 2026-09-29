<?php

namespace Tests\Unit;

use App\Http\Services\BusinessApprovalService;
use App\Models\Bank;
use App\Models\BusinessApprovalRequest;
use App\Models\Cash;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessApprovalOrderTest extends TestCase
{
    private $service;
    private $staff;
    private $manager;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['business_approval_requests', 'daily_cash_registers', 'banks', 'cash',
            'transactions', 'vehicles', 'order_vehicle_details', 'orders', 'users', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('roles_permissions');
        Schema::dropIfExists('permissions');
        Schema::create('roles', function (Blueprint $t) {
            $t->increments('id'); $t->string('slug'); $t->string('name')->nullable(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('email'); $t->integer('role_id');
            $t->integer('store_id')->nullable(); $t->integer('is_admin')->default(0);
            $t->string('status')->default('active'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id'); $t->integer('store_id'); $t->string('order_status');
            $t->bigInteger('total'); $t->bigInteger('approved_discount_amount')->default(0);
            $t->boolean('contract_is_locked')->default(false); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('order_vehicle_details', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_id'); $t->integer('vehicle_id');
            $t->dateTime('completed_at')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->increments('id'); $t->string('status'); $t->timestamps();
        });
        Schema::create('transactions', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_id'); $t->string('name'); $t->string('type');
            $t->bigInteger('value'); $t->text('note')->nullable(); $t->string('status');
            $t->integer('user_id')->nullable(); $t->integer('store_id'); $t->integer('payment_method')->nullable();
            $t->integer('bank_id')->nullable(); $t->integer('cash_id')->nullable(); $t->timestamps();
        });
        Schema::create('cash', function (Blueprint $t) {
            $t->increments('id'); $t->integer('store_id'); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('banks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('store_id'); $t->timestamps();
        });
        Schema::create('daily_cash_registers', function (Blueprint $t) {
            $t->increments('id'); $t->integer('store_id'); $t->date('register_date'); $t->string('status'); $t->timestamps();
        });
        require_once __DIR__.'/../../database/migrations/2026_09_30_000001_create_business_approval_requests.php';
        (new \CreateBusinessApprovalRequests())->up();
        DB::table('roles')->insert([
            ['id' => 4, 'slug' => 'quan-ly-cua-hang'], ['id' => 5, 'slug' => 'nhan-vien'],
        ]);
        $this->staff = User::create(['name' => 'Quầy', 'email' => 'counter@example.test', 'role_id' => 5, 'store_id' => 1]);
        $this->manager = User::create(['name' => 'Trưởng phòng', 'email' => 'manager@example.test', 'role_id' => 4, 'store_id' => 1]);
        $this->order = Order::create(['store_id' => 1, 'order_status' => 'renting', 'total' => 1000000]);
        $this->service = app(BusinessApprovalService::class);
    }

    public function testDiscountRequiresDifferentApproverAndKeepsReason(): void
    {
        $request = $this->service->submitOrder($this->order->id, 'order_discount',
            ['amount' => 300000, 'reason' => 'Khách thân thiết'], $this->staff);
        $this->assertSame('submitted', $request->status);
        $this->expectException(AuthorizationException::class);
        $this->service->decide($request->id, true, 'Đồng ý giảm giá', $this->staff);
    }

    public function testDiscountApprovalChangesTotalOnceAndRejectsStalePrice(): void
    {
        $request = $this->service->submitOrder($this->order->id, 'order_discount',
            ['amount' => 300000, 'reason' => 'Khuyến mại đặc biệt'], $this->staff);
        $this->service->decide($request->id, true, 'Đồng ý giảm giá', $this->manager);
        $this->assertEquals(700000, $this->order->fresh()->total);
        $this->assertEquals(300000, $this->order->fresh()->approved_discount_amount);
        $this->assertSame('approved', BusinessApprovalRequest::findOrFail($request->id)->status);
        try {
            $this->service->decide($request->id, true, 'Duyệt lại', $this->manager);
            $this->fail('Repeated approval must fail.');
        } catch (ValidationException $e) {
            $this->assertEquals(700000, $this->order->fresh()->total);
        }
    }

    public function testCancellationApprovalAndSeparateRefundAreIdempotent(): void
    {
        Transaction::create(['order_id' => $this->order->id, 'name' => 'deposit', 'type' => 'in',
            'value' => 500000, 'status' => 'approved', 'store_id' => 1]);
        Transaction::create(['order_id' => $this->order->id, 'name' => 'addon', 'type' => 'addon',
            'value' => 100000, 'status' => 'approved', 'store_id' => 1]);
        $cash = Cash::create(['store_id' => 1, 'status' => 'Active']);
        $request = $this->service->submitOrder($this->order->id, 'order_cancel',
            ['reason' => 'Khách đổi lịch'], $this->staff);
        $this->service->decide($request->id, true, 'Đã kiểm tra xe', $this->manager);
        $this->assertSame('cancel_pending_settlement', $this->order->fresh()->order_status);
        $this->assertEquals(600000, BusinessApprovalRequest::findOrFail($request->id)->payload['expected_refund']);
        $this->assertEquals(0, Transaction::where('type', 'out')->count());
        $this->service->settleCancellation($request->id,
            ['channel' => 'cash', 'source_id' => $cash->id, 'note' => 'Đã trả khách tại quầy'], $this->manager);
        $this->assertEquals(600000, Transaction::where('type', 'out')->sum('value'));
        $this->assertSame('cancelled', $this->order->fresh()->order_status);
        try {
            $this->service->settleCancellation($request->id,
                ['channel' => 'cash', 'source_id' => $cash->id, 'note' => 'Trả lại'], $this->manager);
            $this->fail('Repeated settlement must fail.');
        } catch (ValidationException $e) {
            $this->assertSame(1, Transaction::where('type', 'out')->count());
        }
    }

    public function testManagerCannotApproveOrderFromAnotherStore(): void
    {
        $request = $this->service->submitOrder($this->order->id, 'order_cancel',
            ['reason' => 'Khách hủy'], $this->staff);
        $other = User::create(['name' => 'Trưởng phòng khác', 'email' => 'other@example.test',
            'role_id' => 4, 'store_id' => 2]);
        $this->expectException(AuthorizationException::class);
        $this->service->decide($request->id, true, 'Duyệt', $other);
    }

    public function testApprovalInboxIsLimitedToOwnStoreOrOwnRequests(): void
    {
        $mine = $this->service->submitOrder($this->order->id, 'order_cancel',
            ['reason' => 'Khách hủy'], $this->staff);
        $otherOrder = Order::create(['store_id' => 2, 'order_status' => 'renting', 'total' => 100000]);
        $otherStaff = User::create(['name' => 'Quầy khác', 'email' => 'other-counter@example.test',
            'role_id' => 5, 'store_id' => 2]);
        $other = $this->service->submitOrder($otherOrder->id, 'order_cancel',
            ['reason' => 'Khách hủy'], $otherStaff);
        $managerIds = $this->service->index(['subject_type' => 'order'], $this->manager)
            ->getCollection()->pluck('id')->all();
        $staffIds = $this->service->index(['subject_type' => 'order'], $this->staff)
            ->getCollection()->pluck('id')->all();
        $this->assertContains($mine->id, $managerIds);
        $this->assertNotContains($other->id, $managerIds);
        $this->assertSame([$mine->id], $staffIds);
    }
}
