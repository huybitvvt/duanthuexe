<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vehicle;
use App\Models\Bank;
use App\Models\Transaction;
use App\Entities\Customer;
use App\Models\ContractNumberCounter;
use App\Http\Services\ContractNumberService;
use App\Http\Services\ContractDocumentBuilder;
use App\Http\Services\OrderService;
use App\Repositories\OrderRepositoryEloquent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class HimotoContractDocumentTest extends TestCase
{
    protected $orderService;
    protected $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();
        $this->orderService = app(OrderService::class);
        $this->orderRepository = app(OrderRepositoryEloquent::class);
    }

    protected function createTestTables()
    {
        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('contract_number_counters');
        Schema::create('contract_number_counters', function ($table) {
            $table->increments('id');
            $table->date('number_date')->unique();
            $table->integer('last_number')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('customers');
        Schema::create('customers', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('id_card')->nullable();
            $table->date('id_card_issued_on')->nullable();
            $table->string('id_card_issued_by')->nullable();
            $table->text('relatives')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('orders');
        Schema::create('orders', function ($table) {
            $table->increments('id');
            $table->integer('customer_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_status')->default('renting');
            $table->string('contract_number')->nullable();
            $table->timestamp('contract_issued_at')->nullable();
            $table->date('contract_signed_on')->nullable();
            $table->date('contract_authorization_date')->nullable();
            $table->string('contract_authorization_party_name')->nullable();
            $table->integer('contract_responsible_user_id')->nullable();
            $table->text('contract_collateral_description')->nullable();
            $table->string('contract_signer_a_name')->nullable();
            $table->string('contract_signer_b_name')->nullable();
            $table->string('return_signer_a_name')->nullable();
            $table->string('return_signer_b_name')->nullable();
            $table->text('return_additional_note')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('pid', 15, 2)->default(0);
            $table->decimal('first_deposit_amount', 15, 2)->default(0);
            $table->decimal('additional_deposit_amount', 15, 2)->default(0);
            $table->decimal('total_rental_fees', 15, 2)->default(0);
            $table->decimal('default_refund_amount', 15, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->text('contract_snapshot')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('license')->nullable();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('color')->nullable();
            $table->integer('year')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::dropIfExists('order_vehicle_details');
        Schema::create('order_vehicle_details', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('vehicle_id')->nullable();
            $table->timestamp('rent_at')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total_money', 15, 2)->default(0);
            $table->decimal('hiring_fee', 15, 2)->default(0);
            $table->string('driver_name')->nullable();
            $table->string('driver_license_number')->nullable();
            $table->date('driver_license_issued_on')->nullable();
            $table->integer('borrow_hats')->default(0);
            $table->integer('borrow_raincoats')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('banks');
        Schema::create('banks', function ($table) {
            $table->increments('id');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_type')->nullable();
            $table->string('owner_type')->default('unknown');
            $table->string('owner_name')->nullable();
            $table->integer('store_id')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('transactions');
        Schema::create('transactions', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('order_item_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->string('status')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('payment_method')->default(1);
            $table->integer('bank_id')->nullable();
            $table->string('bank_owner_type')->nullable();
            $table->integer('cash_id')->nullable();
            $table->string('desc')->nullable();
            $table->string('object_name')->nullable();
            $table->string('object_type')->nullable();
            $table->integer('object_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * C02: Preview 10 times does NOT mutate database, does NOT increment counter.
     */
    public function testPreviewDoesNotMutateDatabaseOrConsumeCounter()
    {
        $counterCountBefore = ContractNumberCounter::count();
        $orderCountBefore = Order::count();

        $formData = [
            'customer_name' => 'Nguyễn Văn Test',
            'customer_phone' => '0988888888',
            'customer_id_card' => '001200000001',
            'customer_address' => 'Số 1 Đống Đa, Hà Nội',
            'contract_signed_on' => '2026-09-15',
            'order_items' => [
                [
                    'vehicle_id' => 1,
                    'vehicle_name' => 'Honda Wave Alpha',
                    'license' => '29B1-99999',
                    'driver_name' => 'Nguyễn Văn Test',
                    'borrow_hats' => 2,
                    'borrow_raincoats' => 1,
                    'rent_at' => '2026-09-15 08:00',
                    'return_at' => '2026-09-18 08:00',
                ]
            ],
            'total_rental_fees' => 450000,
            'deposit_amount' => 1000000,
        ];

        for ($i = 0; $i < 10; $i++) {
            $dto = ContractDocumentBuilder::buildFromFormData($formData);
            $this->assertTrue($dto['is_preview']);
            $this->assertEquals('Chưa cấp số', $dto['contract_number']);
            $this->assertEquals('Nguyễn Văn Test', $dto['customer']['name']);
            $this->assertEquals(2, $dto['equipment']['total_hats']);
        }

        $this->assertEquals($counterCountBefore, ContractNumberCounter::count(), 'Counter count must not change on preview');
        $this->assertEquals($orderCountBefore, Order::count(), 'Orders count must not change on preview');
    }

    /**
     * C03: Locked snapshot is immutable even when customer or order fields change.
     */
    public function testLockedSnapshotIsImmutable()
    {
        $customer = Customer::create([
            'name' => 'Khách Ban Đầu',
            'phone' => '0911111111',
            'id_card' => '012345678901',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'contract_signed_on' => '2026-09-15',
            'total' => 300000,
            'first_deposit_amount' => 500000,
            'order_status' => 'renting',
        ]);

        $this->orderService->lockContract($order);
        $order->refresh();

        $this->assertTrue($order->contract_snapshot['is_locked']);
        $this->assertNotEmpty($order->contract_number);

        // Update customer name in database
        $customer->name = 'Khách Đã Thay Đổi';
        $customer->save();

        // Calling maybeGenerateContractSnapshot should return locked original snapshot
        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);
        $this->assertEquals('Khách Ban Đầu', $snapshot['customer']['name']);

        $docDto = ContractDocumentBuilder::buildFromOrder($order);
        $this->assertEquals('Khách Ban Đầu', $docDto['customer']['name']);
        $this->assertFalse($docDto['is_preview']);
        $this->assertEquals($order->contract_number, $docDto['contract_number']);
    }

    /**
     * P01: Bank owner_type and Transaction bank_owner_type.
     */
    public function testDocumentUsesSnapshotVehiclePricingAndVietnameseDates()
    {
        $order = Order::create([
            'contract_snapshot' => [
                'is_locked' => true, 'contract_number' => '2026/01/02-0001', 'signed_on' => '2026-01-02',
                'vehicles' => [['vehicle_name' => 'FELIZ', 'brand' => 'VinFast', 'unit_price' => 150000,
                    'rent_at' => '02/01/2026 09:00', 'return_at' => '05/01/2026 09:00']],
                'payment' => ['rental_fees' => 450000, 'transactions_summary' => [
                    ['name' => 'order:deposit:1', 'type' => 'in', 'value' => 1000000],
                    ['name' => 'order:rental_fees', 'type' => 'in', 'value' => 200000],
                ]],
            ],
            'pid' => 1200000,
        ]);
        $doc = ContractDocumentBuilder::buildFromOrder($order);
        $this->assertEquals('FELIZ', $doc['primary_vehicle']['name']);
        $this->assertEquals('VinFast', $doc['primary_vehicle']['brand']);
        $this->assertEquals(150000, $doc['pricing']['unit_price']);
        $this->assertEquals(200000, $doc['pricing']['paid_amount']);
        $this->assertEquals('02/01/2026 09:00', $doc['rent_time']['start']['formatted']);
        $this->assertFalse($doc['is_preview']);
        $this->assertEquals('2026/01/02-0001', $doc['contract_number_label']);
    }

    public function testPreviewDoesNotInventReceiptOrVehicleAttributes()
    {
        $dto = ContractDocumentBuilder::buildFromFormData([
            'total_rental_fees' => 300000, 'order_items' => [['vehicle_name' => 'Unknown']]
        ]);
        $this->assertEquals(0, $dto['pricing']['paid_amount']);
        $this->assertEquals('', $dto['primary_vehicle']['brand']);
        $this->assertEquals('', $dto['primary_vehicle']['color']);
        $this->assertEquals('', $dto['primary_vehicle']['year']);
        $this->assertEquals('', $dto['deposit']['collateral_description']);
    }

    public function testLockPreservesLegacyNumberWithoutConsumingCounter()
    {
        $order = Order::create(['contract_snapshot' => ['is_locked' => true, 'contract_number' => '2020/01/02-0042']]);
        $before = ContractNumberCounter::count();
        $order = $this->orderService->lockContract($order);
        $this->assertEquals('2020/01/02-0042', $order->contract_number);
        $this->assertEquals($before, ContractNumberCounter::count());
    }

    public function testLockedDocumentRemainsStableAfterOrderAndConfigurationChanges()
    {
        $order = Order::create(['contract_signed_on' => '2026-01-02', 'order_status' => 'renting', 'total' => 300000]);
        $order = $this->orderService->lockContract($order);
        $before = ContractDocumentBuilder::buildFromOrder($order);
        $order->contract_signed_on = '2026-02-01';
        $order->contract_signer_a_name = 'Changed';
        $order->total = 999999;
        $order->save();
        config(['contract.company_name' => 'Changed company']);
        $this->assertEquals($before, ContractDocumentBuilder::buildFromOrder($order->fresh()));
    }

    public function testBankOwnerTypeAndTransactionClassification()
    {
        $bankPersonal = Bank::create([
            'bank_name' => 'Vietcombank',
            'account_number' => '1234567890',
            'owner_name' => 'Nguyễn Văn A',
            'owner_type' => Bank::OWNER_PERSONAL,
        ]);

        $bankCompany = Bank::create([
            'bank_name' => 'MB Bank',
            'account_number' => '9876543210',
            'owner_name' => 'CÔNG TY CP HIMOTO VIỆT NAM',
            'owner_type' => Bank::OWNER_COMPANY,
        ]);

        $this->assertEquals('personal', $bankPersonal->owner_type);
        $this->assertEquals('company', $bankCompany->owner_type);

        $trans = Transaction::create([
            'name' => 'Thu tiền thuê xe',
            'type' => Transaction::THU,
            'value' => 500000,
            'payment_method' => 2, // CK
            'bank_id' => $bankCompany->id,
            'bank_owner_type' => $bankCompany->owner_type,
        ]);

        $this->assertEquals('company', $trans->bank_owner_type);
    }
}
