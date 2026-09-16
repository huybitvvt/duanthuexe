<?php

namespace Tests\Unit;

use App\Http\Services\WarehouseService;
use App\Http\Services\VehicleTransferService;
use App\Models\ContractAmendment;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocationEvent;
use App\Models\VehicleTransfer;
use App\Models\VehicleTransferItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HimotoWarehouseTransferTest extends TestCase
{
    protected $warehouseService;
    protected $transferService;
    protected $storeA;
    protected $storeB;
    protected $adminUser;
    protected $staffUserA;
    protected $staffUserB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        $this->warehouseService = app(WarehouseService::class);
        $this->transferService = app(VehicleTransferService::class);

        // Seed 2 physical stores
        $this->storeA = Store::create([
            'store_name' => 'Cơ sở Cầu Giấy',
            'store_address' => '123 Cầu Giấy, Hà Nội',
            'kind' => Store::KIND_PHYSICAL,
            'code' => 'CS-CG',
            'status' => 'active'
        ]);

        $this->storeB = Store::create([
            'store_name' => 'Cơ sở Đống Đa',
            'store_address' => '456 Xã Đàn, Hà Nội',
            'kind' => Store::KIND_PHYSICAL,
            'code' => 'CS-DD',
            'status' => 'active'
        ]);

        // Users
        $this->adminUser = User::create([
            'name' => 'Admin Himoto',
            'email' => 'admin@himoto.vn',
            'password' => bcrypt('123456'),
            'role_id' => 1,
            'store_id' => $this->storeA->id,
            'status' => 'active',
        ]);

        $this->staffUserA = User::create([
            'name' => 'Nhân viên Cầu Giấy',
            'email' => 'staff.a@himoto.vn',
            'password' => bcrypt('123456'),
            'role_id' => 3,
            'store_id' => $this->storeA->id,
            'status' => 'active',
        ]);

        $this->staffUserB = User::create([
            'name' => 'Nhân viên Đống Đa',
            'email' => 'staff.b@himoto.vn',
            'password' => bcrypt('123456'),
            'role_id' => 3,
            'store_id' => $this->storeB->id,
            'status' => 'active',
        ]);
    }

    protected function createTestTables()
    {
        Schema::dropIfExists('roles');
        Schema::create('roles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Quản trị viên', 'slug' => 'quan-tri-vien'],
            ['id' => 2, 'name' => 'Quản lý cơ sở', 'slug' => 'quan-ly-co-so'],
            ['id' => 3, 'name' => 'Nhân viên', 'slug' => 'nhan-vien'],
        ]);

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('store_address')->nullable();
            $table->string('store_phone')->nullable();
            $table->string('kind')->default('physical');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('role_id')->default(3);
            $table->integer('store_id')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('year')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('current_store_id')->nullable();
            $table->string('license')->nullable();
            $table->string('chassis')->nullable();
            $table->string('engine')->nullable();
            $table->string('status')->default('ready');
            $table->string('color')->nullable();
            $table->integer('odometer')->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('orders');
        Schema::create('orders', function ($table) {
            $table->increments('id');
            $table->string('order_type')->default('car_rental');
            $table->integer('customer_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_status')->default('using');
            $table->string('contract_number')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('order_vehicle_details');
        Schema::create('order_vehicle_details', function ($table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('vehicle_id');
            $table->timestamp('rent_at')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_transfers');
        Schema::create('vehicle_transfers', function ($table) {
            $table->increments('id');
            $table->string('transfer_code')->unique();
            $table->string('type')->default('store_to_store');
            $table->integer('from_store_id')->nullable();
            $table->integer('to_store_id')->nullable();
            $table->string('status')->default('dispatched');
            $table->integer('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->integer('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->integer('order_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_transfer_items');
        Schema::create('vehicle_transfer_items', function ($table) {
            $table->increments('id');
            $table->integer('transfer_id');
            $table->integer('vehicle_id');
            $table->string('source_status')->nullable();
            $table->string('target_status')->nullable();
            $table->integer('odometer_out')->nullable();
            $table->integer('odometer_in')->nullable();
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_location_events');
        Schema::create('vehicle_location_events', function ($table) {
            $table->increments('id');
            $table->integer('vehicle_id');
            $table->integer('from_store_id')->nullable();
            $table->integer('to_store_id')->nullable();
            $table->string('event_type');
            $table->string('ref_type')->nullable();
            $table->integer('ref_id')->nullable();
            $table->integer('odometer')->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('contract_amendments');
        Schema::create('contract_amendments', function ($table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->string('amendment_code')->nullable();
            $table->string('amendment_type')->default('vehicle_exchange');
            $table->integer('old_vehicle_id')->nullable();
            $table->integer('new_vehicle_id')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->decimal('price_difference', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test W01: Role permission scoping on store vehicle details vs summary.
     */
    public function test_w01_permissions_scoping_between_admin_and_staff()
    {
        // 1. Staff A can see summary cards of all stores
        $summary = $this->warehouseService->getSummary($this->staffUserA);
        $this->assertIsArray($summary);
        $this->assertGreaterThanOrEqual(2, count($summary));

        // 2. Staff A can access vehicles of Store A
        $storeAVehicles = $this->warehouseService->getStoreVehicles($this->storeA->id, [], $this->staffUserA);
        $this->assertEquals($this->storeA->id, $storeAVehicles['store']['id']);

        // 3. Staff A is forbidden from viewing detailed vehicles of Store B
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->warehouseService->getStoreVehicles($this->storeB->id, [], $this->staffUserA);
    }

    /**
     * Test W01: Admin can view vehicles from any store.
     */
    public function test_w01_admin_can_view_any_store_vehicles()
    {
        $storeBVehicles = $this->warehouseService->getStoreVehicles($this->storeB->id, [], $this->adminUser);
        $this->assertEquals($this->storeB->id, $storeBVehicles['store']['id']);
    }

    /**
     * Test W02: Flow A - Dispatch, In-transit state, no double counting, and Receive.
     */
    public function test_w02_flow_a_store_transfer_lifecycle_and_aggregate_accuracy()
    {
        // Create vehicle at Store A
        $vehicle = Vehicle::create([
            'name' => 'Honda Vision 2023',
            'brand' => 'Honda',
            'type' => Vehicle::TYPE_XEGA,
            'year' => '2023',
            'store_id' => $this->storeA->id,
            'current_store_id' => $this->storeA->id,
            'license' => '29B1-12345',
            'status' => Vehicle::STATUS_READY,
            'odometer' => 1500,
        ]);

        // Initial summary check
        $summaryBefore = collect($this->warehouseService->getSummary($this->adminUser))->keyBy('id');
        $this->assertEquals(1, $summaryBefore[$this->storeA->id]['ready']);
        $this->assertEquals(0, $summaryBefore[$this->storeB->id]['ready']);

        // 1. Dispatch vehicle from Store A to Store B
        $transfer = $this->transferService->dispatchStoreTransfer([
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'vehicle_ids' => [$vehicle->id],
            'reason' => 'Tăng cường xe sang Đống Đa',
        ], $this->staffUserA);

        $this->assertEquals(VehicleTransfer::STATUS_DISPATCHED, $transfer->status);
        $vehicle->refresh();
        $this->assertEquals(Vehicle::STATUS_IN_TRANSIT, $vehicle->status);

        // Verify summary during transit: Store A ready decreased by 1, Store B ready NOT yet increased
        $summaryTransit = collect($this->warehouseService->getSummary($this->adminUser))->keyBy('id');
        $this->assertEquals(0, $summaryTransit[$this->storeA->id]['ready']);
        $this->assertEquals(0, $summaryTransit[$this->storeB->id]['ready']);
        $this->assertEquals(1, $summaryTransit[$this->storeA->id]['in_transit']);

        // 2. Cannot transfer again while in transit
        try {
            $this->transferService->dispatchStoreTransfer([
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'vehicle_ids' => [$vehicle->id],
            ], $this->staffUserA);
            $this->fail('Expected ValidationException when transferring an in-transit vehicle.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // 3. Receive vehicle at Store B
        $received = $this->transferService->receiveStoreTransfer($transfer->id, [
            'odometers' => [$vehicle->id => 1520],
            'condition_notes' => [$vehicle->id => 'Xe nguyên vẹn, xăng đầy bình'],
        ], $this->staffUserB);

        $this->assertEquals(VehicleTransfer::STATUS_COMPLETED, $received->status);
        $vehicle->refresh();
        $this->assertEquals(Vehicle::STATUS_READY, $vehicle->status);
        $this->assertEquals($this->storeB->id, $vehicle->current_store_id);
        $this->assertEquals(1520, $vehicle->odometer);

        // Summary after receiving: Store B ready is now 1, in_transit is 0
        $summaryAfter = collect($this->warehouseService->getSummary($this->adminUser))->keyBy('id');
        $this->assertEquals(0, $summaryAfter[$this->storeA->id]['ready']);
        $this->assertEquals(1, $summaryAfter[$this->storeB->id]['ready']);
        $this->assertEquals(0, $summaryAfter[$this->storeA->id]['in_transit']);

        // 4. Verify Immutable Location Events
        $history = $this->transferService->getVehicleMovementHistory($vehicle->id);
        $this->assertCount(2, $history['events']);
        $eventTypes = collect($history['events'])->pluck('event_type')->all();
        $this->assertContains(VehicleLocationEvent::EVENT_TRANSFER_DISPATCH, $eventTypes);
        $this->assertContains(VehicleLocationEvent::EVENT_TRANSFER_RECEIVE, $eventTypes);
    }

    /**
     * Test W03: Flow B - Customer returns vehicle at a different branch store.
     */
    public function test_w03_flow_b_return_different_store()
    {
        $vehicle = Vehicle::create([
            'name' => 'Yamaha Grande 2023',
            'brand' => 'Yamaha',
            'type' => Vehicle::TYPE_XEGA,
            'year' => '2023',
            'store_id' => $this->storeA->id,
            'current_store_id' => $this->storeA->id,
            'license' => '29B1-99999',
            'status' => Vehicle::STATUS_USING,
            'odometer' => 5000,
        ]);

        $order = Order::create([
            'order_type' => 'car_rental',
            'order_status' => 'using',
            'store_id' => $this->storeA->id, // Revenue belongs to Store A
            'contract_number' => '2026/09/15-0001',
            'total_amount' => 500000,
        ]);

        OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'rent_at' => '2026-09-15 08:00:00',
            'return_at' => '2026-09-15 18:00:00',
        ]);

        // Customer returns at Store B instead of Store A
        $result = $this->transferService->processReturnDifferentStore($order->id, $this->storeB->id, [
            'odometer' => 5120,
            'condition_notes' => 'Khách trả tại Đống Đa theo thỏa thuận',
        ], $this->staffUserB);

        $this->assertEquals($this->storeB->id, $result['new_current_store_id']);
        $this->assertEquals($this->storeA->id, $result['original_store_id']);

        $vehicle->refresh();
        $order->refresh();

        // Vehicle physical location is now Store B and ready for next customer
        $this->assertEquals($this->storeB->id, $vehicle->current_store_id);
        $this->assertEquals(Vehicle::STATUS_READY, $vehicle->status);
        $this->assertEquals(5120, $vehicle->odometer);

        // Historical order store (revenue store) remains Store A untouched
        $this->assertEquals($this->storeA->id, $order->store_id);

        // Check location event
        $events = VehicleLocationEvent::where('vehicle_id', $vehicle->id)->get();
        $this->assertCount(1, $events);
        $this->assertEquals(VehicleLocationEvent::EVENT_ORDER_RETURN_DIFFERENT, $events->first()->event_type);
        $this->assertEquals($this->storeA->id, $events->first()->from_store_id);
        $this->assertEquals($this->storeB->id, $events->first()->to_store_id);
    }

    /**
     * Test W03: Flow C - Vehicle exchange during an active rental (broken vehicle replacement).
     */
    public function test_w03_flow_c_vehicle_exchange_and_contract_amendment()
    {
        // Old vehicle rented out
        $oldVehicle = Vehicle::create([
            'name' => 'Honda Lead 2022',
            'brand' => 'Honda',
            'type' => Vehicle::TYPE_XEGA,
            'year' => '2022',
            'store_id' => $this->storeA->id,
            'current_store_id' => $this->storeA->id,
            'license' => '29B1-11111',
            'status' => Vehicle::STATUS_USING,
            'odometer' => 8000,
        ]);

        // New vehicle available at Store A
        $newVehicle = Vehicle::create([
            'name' => 'Honda Lead 2024',
            'brand' => 'Honda',
            'type' => Vehicle::TYPE_XEGA,
            'year' => '2024',
            'store_id' => $this->storeA->id,
            'current_store_id' => $this->storeA->id,
            'license' => '29B1-22222',
            'status' => Vehicle::STATUS_READY,
            'odometer' => 500,
        ]);

        $order = Order::create([
            'order_type' => 'car_rental',
            'order_status' => 'using',
            'store_id' => $this->storeA->id,
            'contract_number' => '2026/09/15-0002',
            'total_amount' => 600000,
            'deposit_amount' => 2000000,
        ]);

        OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $oldVehicle->id,
            'rent_at' => '2026-09-15 08:00:00',
            'return_at' => '2026-09-17 08:00:00',
        ]);

        // Exchange vehicle due to engine issue
        $exchangeResult = $this->transferService->processVehicleExchange(
            $order->id,
            $oldVehicle->id,
            $newVehicle->id,
            [
                'reason' => 'Xe bị hỏng ắc quy giữa đường, đổi xe tương đương',
                'condition_notes' => 'Xe cũ trầy xước nhẹ cánh yếm',
                'price_difference' => 0,
                'old_vehicle_odometer' => 8150,
                'new_vehicle_odometer' => 500,
            ],
            $this->staffUserA
        );

        $oldVehicle->refresh();
        $newVehicle->refresh();
        $order->refresh();

        // 1. Old vehicle status is now repairing
        $this->assertEquals(Vehicle::STATUS_REPAIRING, $oldVehicle->status);
        $this->assertEquals(8150, $oldVehicle->odometer);

        // 2. New vehicle status is now using
        $this->assertEquals(Vehicle::STATUS_USING, $newVehicle->status);

        // 3. Order vehicle detail now references the new vehicle
        $updatedDetail = OrderVehicleDetail::where('order_id', $order->id)->first();
        $this->assertEquals($newVehicle->id, $updatedDetail->vehicle_id);

        // 4. Contract amendment was created
        $amendment = ContractAmendment::where('order_id', $order->id)->first();
        $this->assertNotNull($amendment);
        $this->assertEquals($oldVehicle->id, $amendment->old_vehicle_id);
        $this->assertEquals($newVehicle->id, $amendment->new_vehicle_id);
        $this->assertEquals(ContractAmendment::TYPE_VEHICLE_EXCHANGE, $amendment->amendment_type);

        // 5. Check location ledger events
        $oldEvents = VehicleLocationEvent::where('vehicle_id', $oldVehicle->id)->get();
        $newEvents = VehicleLocationEvent::where('vehicle_id', $newVehicle->id)->get();
        $this->assertEquals(VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_OUT, $oldEvents->last()->event_type);
        $this->assertEquals(VehicleLocationEvent::EVENT_VEHICLE_EXCHANGE_IN, $newEvents->last()->event_type);
    }
}
