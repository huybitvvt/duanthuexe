<?php

namespace Tests\Feature\Himoto;

use App\Entities\Customer;
use App\Entities\SellOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleAndCustomerWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('stores', function ($t) { $t->increments('id'); $t->string('store_name'); });
        Schema::create('users', function ($t) { $t->increments('id'); $t->string('name'); $t->softDeletes(); });
        Schema::create('customers', function ($t) {
            $t->increments('id'); $t->string('name'); $t->string('phone')->nullable(); $t->string('email')->nullable();
            $t->string('address')->nullable(); $t->string('id_card')->nullable(); $t->integer('status')->default(1);
            $t->string('relatives')->nullable(); $t->date('id_card_issued_on')->nullable(); $t->timestamps();
        });
        Schema::create('vehicles', function ($t) {
            $t->increments('id'); $t->string('name'); $t->string('status'); $t->integer('store_id');
            $t->integer('current_store_id')->nullable(); $t->decimal('cost_price', 18, 2)->default(100); $t->timestamps();
        });
        Schema::create('sell_orders', function ($t) {
            $t->increments('id'); $t->integer('store_id'); $t->integer('customer_id'); $t->string('sale_id')->nullable();
            $t->decimal('price', 18, 2); $t->decimal('price_profit', 18, 2); $t->timestamps();
        });
        Schema::create('sell_order_items', function ($t) {
            $t->increments('id'); $t->integer('order_id'); $t->integer('vehicle_id'); $t->decimal('price', 18, 2);
            $t->decimal('vehicle_cost_price', 18, 2); $t->string('desc')->nullable(); $t->timestamps();
        });
        Schema::create('orders', function ($t) { $t->increments('id'); $t->integer('customer_id'); $t->string('order_status'); $t->softDeletes(); });
        Schema::create('order_vehicle_details', function ($t) { $t->increments('id'); $t->integer('order_id'); $t->integer('vehicle_id'); $t->softDeletes(); });
        DB::table('stores')->insert(['id' => 31, 'store_name' => 'CS1']);
        DB::table('users')->insert(['id' => 1, 'name' => 'Fixture admin']);
        DB::table('vehicles')->insert([['id' => 1, 'name' => 'Ready A', 'status' => 'ready', 'store_id' => 31],
            ['id' => 2, 'name' => 'Ready B', 'status' => 'ready', 'store_id' => 31],
            ['id' => 3, 'name' => 'Renting', 'status' => 'using', 'store_id' => 31]]);
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs(new User(['id' => 1, 'role_id' => 1, 'status' => 'active']), 'api');
    }

    private function sale(int $vehicleId = 1): array
    {
        return ['store_id' => 31, 'price' => 200, 'price_profit' => 100,
            'customer' => ['name' => 'Fixture customer', 'phone' => '0901234567', 'id_card' => '012345678901'],
            'items' => [['vehicle_id' => $vehicleId, 'price' => 200, 'vehicle_cost_price' => 100]]];
    }

    public function testSaleRejectsInvalidItemsAndUnavailableVehicleWithoutWrites(): void
    {
        foreach ([[], $this->sale(3), array_merge($this->sale(), ['items' => [$this->sale()['items'][0], $this->sale()['items'][0]]])] as $payload) {
            $this->postJson('/api/auth/order-sell/store', $payload)->assertStatus(422);
            $this->assertSame(0, SellOrder::count());
            $this->assertSame(0, Customer::count());
            $this->assertSame(0, DB::transactionLevel());
        }
        $this->assertSame('using', DB::table('vehicles')->where('id', 3)->value('status'));
    }

    public function testReadyFlagCannotOverrideExistingRental(): void
    {
        DB::table('orders')->insert(['id' => 1, 'customer_id' => 1, 'order_status' => 'renting']);
        DB::table('order_vehicle_details')->insert(['order_id' => 1, 'vehicle_id' => 1]);
        $this->postJson('/api/auth/order-sell/store', $this->sale())->assertStatus(422);
        $this->assertSame(0, SellOrder::count());
    }

    public function testSaleTotalsUseItemsAndStoredCostInsteadOfClientTotals(): void
    {
        $payload = $this->sale();
        $payload['price'] = 1; $payload['price_profit'] = 99999; $payload['items'][0]['vehicle_cost_price'] = 1;
        $this->postJson('/api/auth/order-sell/store', $payload)->assertStatus(200);
        $this->assertEquals(200, SellOrder::first()->price);
        $this->assertEquals(100, SellOrder::first()->price_profit);
        $this->assertEquals(100, DB::table('sell_order_items')->value('vehicle_cost_price'));
    }

    public function testSaleEditAndDeleteRestoreOnlyRemovedVehicles(): void
    {
        $this->postJson('/api/auth/order-sell/store', $this->sale())->assertStatus(200);
        $id = SellOrder::first()->id;
        $this->assertSame('sold', DB::table('vehicles')->where('id', 1)->value('status'));
        $update = $this->sale(2); $update['id'] = $id; $update['order_items'] = $update['items']; unset($update['items']);
        $this->postJson('/api/auth/order-sell/update', $update)->assertStatus(200);
        $this->assertSame('ready', DB::table('vehicles')->where('id', 1)->value('status'));
        $this->assertSame('sold', DB::table('vehicles')->where('id', 2)->value('status'));
        $this->deleteJson('/api/auth/order-sell/'.$id)->assertStatus(200);
        $this->assertSame('ready', DB::table('vehicles')->where('id', 2)->value('status'));
        $this->assertSame(0, DB::table('sell_order_items')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function testCannotReuseVehicleSoldInAnotherSaleAndFailedUpdatePreservesOriginal(): void
    {
        $this->postJson('/api/auth/order-sell/store', $this->sale())->assertStatus(200);
        $id = SellOrder::first()->id;
        $this->postJson('/api/auth/order-sell/store', $this->sale())->assertStatus(422);
        $update = $this->sale(3); $update['id'] = $id; $update['order_items'] = $update['items']; unset($update['items']);
        $this->postJson('/api/auth/order-sell/update', $update)->assertStatus(422);
        $this->assertSame(1, SellOrder::count());
        $this->assertSame(1, (int) DB::table('sell_order_items')->value('vehicle_id'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function testReferencedCustomerCannotBeDeletedButUnusedCustomerCan(): void
    {
        $customer = Customer::create($this->sale()['customer']);
        DB::table('orders')->insert(['customer_id' => $customer->id, 'order_status' => 'completed', 'deleted_at' => '2026-10-01']);
        $this->deleteJson('/api/auth/customers/'.$customer->id)->assertStatus(422);
        $this->assertNotNull(Customer::find($customer->id));
        DB::table('orders')->delete();
        $this->deleteJson('/api/auth/customers/'.$customer->id)->assertStatus(200);
        $this->assertNull(Customer::find($customer->id));
    }

    public function testCustomerInvalidFieldsAreRejectedAndValidPartialUpdateWorks(): void
    {
        $this->postJson('/api/auth/customers', ['name' => [], 'email' => 'not-email', 'phone' => 'x'])->assertStatus(422);
        $this->assertSame(0, Customer::count());
        $this->postJson('/api/auth/customers', $this->sale()['customer'])->assertStatus(200);
        $id = Customer::first()->id;
        $this->putJson('/api/auth/customers/'.$id, ['phone' => 'x'])->assertStatus(422);
        $this->putJson('/api/auth/customers/'.$id, ['name' => 'Updated customer'])->assertStatus(200);
        $this->assertSame('Updated customer', Customer::find($id)->name);
    }
}
