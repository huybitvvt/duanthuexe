<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vehicle;
use App\Entities\Customer;
use App\Models\OrderVehicleDetail;
use App\Models\Transaction;
use App\Models\ContractNumberCounter;
use App\Http\Services\ContractNumberService;
use App\Http\Services\OrderService;
use App\Repositories\OrderRepositoryEloquent;
use App\Validators\OrderValidator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Helpers\CarRentalHelper;
use App\Models\PriceVehicle;

class HimotoContractTest extends TestCase
{
    protected $contractNumberService;
    protected $orderService;
    protected $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();
        Schema::table('order_vehicle_details', function ($t) { $t->decimal('hiring_fee', 15, 2)->default(0); });
        Schema::table('orders', function ($t) {
            $t->string('note')->nullable();
            $t->string('note_payment')->nullable();
            $t->integer('out_dated_at')->nullable();
        });
        Schema::table('vehicles', function ($t) { $t->integer('year')->nullable(); });
        Schema::dropIfExists('pricing');
        Schema::create('pricing', function ($table) {
            $table->increments('id');
            $table->string('type')->nullable();
            $table->string('price_type')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('from_year')->nullable();
            $table->integer('to_year')->nullable();
            $table->integer('from_date')->nullable();
            $table->integer('to_date')->nullable();
            $table->timestamps();
        });
        Schema::dropIfExists('activity_logs');
        Schema::create('activity_logs', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('action')->nullable();
            $table->text('content')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
        $this->contractNumberService = app(ContractNumberService::class);
        $this->orderService = app(OrderService::class);
        $this->orderRepository = app(OrderRepositoryEloquent::class);
    }

    protected function createTestTables()
    {
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
            $table->text('warning')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('orders');
        Schema::create('orders', function ($table) {
            $table->increments('id');
            $table->integer('customer_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_status')->nullable();
            $table->string('contract_number')->nullable();
            $table->date('contract_signed_on')->nullable();
            $table->timestamp('contract_issued_at')->nullable();
            $table->text('contract_snapshot')->nullable();
            $table->string('contract_collateral_description')->nullable();
            $table->date('contract_authorization_date')->nullable();
            $table->string('contract_authorization_party_name')->nullable();
            $table->integer('contract_responsible_user_id')->nullable();
            $table->string('contract_signer_a_name')->nullable();
            $table->string('contract_signer_b_name')->nullable();
            $table->string('return_signer_a_name')->nullable();
            $table->string('return_signer_b_name')->nullable();
            $table->text('return_additional_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('first_deposit_amount', 15, 2)->default(0);
            $table->decimal('additional_deposit_amount', 15, 2)->default(0);
            $table->decimal('outdate_or_early_amount', 15, 2)->default(0);
            $table->decimal('default_refund_amount', 15, 2)->default(0);
            $table->decimal('custom_refund_amount', 15, 2)->default(0);
            $table->decimal('pid', 15, 2)->default(0);
            $table->decimal('return_adjustment_applied', 15, 2)->nullable()->default(0);
            $table->integer('data_version')->nullable()->default(1);
            $table->string('vehicle_ids')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('order_vehicle_details');
        Schema::create('order_vehicle_details', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('vehicle_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('total_money', 15, 2)->default(0);
            $table->decimal('substitute_unit_price', 15, 2)->default(0);
            $table->decimal('handler_price', 15, 2)->default(0);
            $table->decimal('money_out_date', 15, 2)->default(0);
            $table->integer('minute_out_date')->default(0);
            $table->string('driver_name')->nullable();
            $table->string('driver_license_number')->nullable();
            $table->date('driver_license_issued_on')->nullable();
            $table->integer('borrow_hats')->default(0);
            $table->integer('borrow_raincoats')->default(0);
            $table->timestamp('rent_at')->nullable();
            $table->timestamp('return_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicles');
        Schema::create('vehicles', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('license')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('stores');
        Schema::create('stores', function ($table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->string('store_address')->nullable();
            $table->string('store_phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('transactions');
        Schema::create('transactions', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->integer('payment_method')->nullable();
            $table->integer('order_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('leads');
        Schema::create('leads', function ($table) {
            $table->increments('id');
            $table->integer('order_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Test 1: Contract number format and sequence.
     */
    public function testContractNumberFormatAndSequence()
    {
        $date = Carbon::parse('2026-09-14');
        $number1 = $this->contractNumberService->generateForOrder(null, $date);
        $number2 = $this->contractNumberService->generateForOrder(null, $date);

        $this->assertRegExp('/^20260914-\d{4}$/', $number1);
        $this->assertRegExp('/^20260914-\d{4}$/', $number2);
        $this->assertTrue($this->contractNumberService->isValid($number1));
        $this->assertTrue($this->contractNumberService->isValid('2026/09/14-0001'), 'Must accept legacy format for backward compatibility');

        $seq1 = (int) substr($number1, -4);
        $seq2 = (int) substr($number2, -4);
        $this->assertEquals($seq1 + 1, $seq2);
    }

    /**
     * Test 2: Idempotency: Existing contract number is NEVER reissued or altered.
     */
    public function testContractNumberIdempotency()
    {
        $order = new Order();
        $order->contract_number = '2026/09/14-0005';

        $generated = $this->contractNumberService->generateForOrder($order);
        $this->assertEquals('2026/09/14-0005', $generated, 'Must return existing contract number without incrementing');
    }

    /**
     * Test 3: Snapshot immutability.
     */
    public function testContractSnapshotImmutability()
    {
        $order = new Order();
        $order->id = 99901;
        $order->order_status = OrderValidator::ORDER_UNPAID;
        $order->contract_number = '2026/09/14-0001';
        $order->contract_snapshot = [
            'contract_number' => '2026/09/14-0001',
            'customer' => [
                'name' => 'Nguyễn Văn A',
                'id_card' => '001234567890',
            ],
            'return_confirmation' => [
                'signer_a_name' => null,
                'signer_b_name' => null,
            ],
        ];

        // Calling maybeGenerateContractSnapshot on order with existing snapshot returns original snapshot without modification
        $result = $this->orderService->maybeGenerateContractSnapshot($order);
        $this->assertEquals('Nguyễn Văn A', $result['customer']['name']);
        $this->assertEquals('2026/09/14-0001', $result['contract_number']);
    }

    /**
     * Test 4: Dual mapping for CCCD issue date and issue place.
     */
    public function testCccdDualFieldMappingInStoreOrUpdateCustomer()
    {
        // Case A: Frontend sends customer_id_card_issued_on & customer_id_card_issued_by
        $requestA = new Request([
            'customer_name' => 'Test Customer A',
            'customer_phone' => '0912000001',
            'customer_id_card' => '012345678901',
            'customer_id_card_issued_on' => '2022-05-15',
            'customer_id_card_issued_by' => 'Cục Cảnh sát QLHC về TTXH',
        ]);

        $reflection = new \ReflectionClass($this->orderService);
        $method = $reflection->getMethod('storeOrUpdateCustomer');
        $method->setAccessible(true);

        $customerA = $method->invoke($this->orderService, $requestA);
        $this->assertEquals('2022-05-15', Carbon::parse($customerA->id_card_issued_on)->format('Y-m-d'));
        $this->assertEquals('Cục Cảnh sát QLHC về TTXH', $customerA->id_card_issued_by);

        // Case B: Client sends id_card_issued_on & id_card_issued_by
        $requestB = new Request([
            'customer_name' => 'Test Customer B',
            'customer_phone' => '0912000002',
            'customer_id_card' => '012345678902',
            'id_card_issued_on' => '2023-08-20',
            'id_card_issued_by' => 'Công an TP Hà Nội',
        ]);

        $customerB = $method->invoke($this->orderService, $requestB);
        $this->assertEquals('2023-08-20', Carbon::parse($customerB->id_card_issued_on)->format('Y-m-d'));
        $this->assertEquals('Công an TP Hà Nội', $customerB->id_card_issued_by);
    }

    /**
     * Test 5: Safe search query prevents PostgreSQL bigint invalid text syntax error.
     */
    public function testPostgresqlSafeSearchQueryBuilder()
    {
        // 5a. Non-numeric contract number query
        $queryNonNumeric = Order::query();
        $this->orderRepository->applyKeywordFilter($queryNonNumeric, '2026/09/14-0001');
        $sqlNonNumeric = $queryNonNumeric->toSql();

        // Must search contract_number, but MUST NOT contain orders.id = ? or "orders"."id" = ?
        $this->assertStringContainsString('contract_number', $sqlNonNumeric);
        $this->assertFalse(
            strpos($sqlNonNumeric, '"orders"."id" = ?') !== false || strpos($sqlNonNumeric, 'orders.id = ?') !== false,
            'Non-numeric keyword must never generate orders.id = ? clause'
        );
        $this->assertNotContains('2026/09/14-0001', $queryNonNumeric->getBindings(), 'Raw non-numeric string must never be bound as order ID');

        // 5b. Numeric query (e.g. 1234)
        $queryNumeric = Order::query();
        $this->orderRepository->applyKeywordFilter($queryNumeric, '1234');
        $sqlNumeric = $queryNumeric->toSql();

        $this->assertTrue(
            strpos($sqlNumeric, '"orders"."id" = ?') !== false || strpos($sqlNumeric, 'orders.id = ?') !== false,
            'Numeric keyword should include orders.id = ?'
        );
        $this->assertContains(1234, $queryNumeric->getBindings());

        // 5c. Hash prefix numeric query (e.g. #5678)
        $queryHashNumeric = Order::query();
        $this->orderRepository->applyKeywordFilter($queryHashNumeric, '#5678');
        $sqlHashNumeric = $queryHashNumeric->toSql();
        $bindingsHashNumeric = $queryHashNumeric->getBindings();

        $this->assertTrue(
            strpos($sqlHashNumeric, '"orders"."id" = ?') !== false || strpos($sqlHashNumeric, 'orders.id = ?') !== false,
            '#<digits> should include orders.id = ?'
        );
        $this->assertContains(5678, $bindingsHashNumeric, 'Extracted integer 5678 should be bound to orders.id');
    }

    /**
     * Test 6: Validator rules for Himoto contract fields.
     */
    public function testContractFieldValidationRules()
    {
        $rules = OrderValidator::contractRules();

        // 6a. Negative raincoat count fails
        $validatorNegative = Validator::make([
            'order_items' => [
                [
                    'borrow_raincoats' => -3,
                    'borrow_hats' => 2,
                ]
            ]
        ], $rules);
        $this->assertTrue($validatorNegative->fails(), 'Negative borrow_raincoats must fail validation');

        // 6b. Relatives as string instead of array fails
        $validatorInvalidRelatives = Validator::make([
            'relatives' => 'not-an-array'
        ], $rules);
        $this->assertTrue($validatorInvalidRelatives->fails(), 'Non-array relatives must fail validation');

        // 6c. Invalid date string fails
        $validatorInvalidDate = Validator::make([
            'contract_signed_on' => 'not-a-valid-date'
        ], $rules);
        $this->assertTrue($validatorInvalidDate->fails(), 'Invalid contract_signed_on must fail validation');

        // 6d. Valid contract fields pass
        $validatorValid = Validator::make([
            'contract_signed_on' => '2026-09-14',
            'contract_authorization_date' => '2026-09-01',
            'contract_authorization_party_name' => 'Công ty ABC',
            'contract_collateral_description' => 'CCCD gắn chip',
            'contract_signer_a_name' => 'Bà Nguyễn Thu Thủy',
            'contract_signer_b_name' => 'Trần Văn B',
            'relatives' => [
                ['name' => 'Trần Văn C', 'relationship' => 'Bố', 'phone' => '0988776655'],
            ],
            'order_items' => [
                [
                    'driver_name' => 'Trần Văn B',
                    'driver_license_number' => '010123456789',
                    'driver_license_issued_on' => '2020-01-01',
                    'borrow_hats' => 2,
                    'borrow_raincoats' => 1,
                ]
            ]
        ], $rules);
        $this->assertFalse($validatorValid->fails(), 'Valid contract fields must pass validation');
    }

    /**
     * Test 7: Return handover preservation when order is unpaid.
     */
    public function testReturnHandoverPreservedOnUnpaidComplete()
    {
        // Mock order
        $order = Order::create([
            'customer_id' => 1,
            'store_id' => 1,
            'total' => 500000,
            'deposit_amount' => 1000000,
            'first_deposit_amount' => 1000000,
            'additional_deposit_amount' => 0,
            'order_status' => OrderValidator::ORDER_UNPAID,
            'contract_number' => '2026/09/14-9999',
            'contract_snapshot' => [
                'contract_number' => '2026/09/14-9999',
                'customer' => ['name' => 'Khách Test'],
                'return_confirmation' => null,
            ],
        ]);

        $request = new Request([
            'isPaid' => false,
            'order_items' => [],
            'return_signer_a_name' => 'NV Tiếp Nhận A',
            'return_signer_b_name' => 'Khách Trả B',
            'return_additional_note' => 'Xe trầy xước nhẹ cánh trái',
            'completed_at' => '14-09-2026 18:00:00',
        ]);

        $this->orderService->complete($request, $order);

        $order->refresh();
        $this->assertEquals(OrderValidator::ORDER_UNPAID, $order->order_status, 'Status must be ORDER_UNPAID');
        $this->assertEquals('NV Tiếp Nhận A', $order->return_signer_a_name, 'Return signer A must be saved');
        $this->assertEquals('Khách Trả B', $order->return_signer_b_name, 'Return signer B must be saved');
        $this->assertEquals('Xe trầy xước nhẹ cánh trái', $order->return_additional_note, 'Return note must be saved');

        $this->assertNotNull($order->contract_snapshot);
        $this->assertEquals('NV Tiếp Nhận A', $order->contract_snapshot['return_confirmation']['signer_a_name']);
        $this->assertEquals('Khách Trả B', $order->contract_snapshot['return_confirmation']['signer_b_name']);
        $this->assertEquals('Xe trầy xước nhẹ cánh trái', $order->contract_snapshot['return_confirmation']['additional_note']);
    }

    /**
     * Test 8: Snapshot pricing mode and transactions capture.
     */
    public function testContractSnapshotPricingAndPaymentSummary()
    {
        $customer = Customer::create([
            'name' => 'Nguyễn Thị Hoa',
            'phone' => '0901234567',
            'id_card' => '079123456789',
        ]);

        $store = Store::create([
            'store_name' => 'Chi nhánh Tây Hồ',
            'store_address' => '123 Lạc Long Quân',
            'store_phone' => '0241234567',
        ]);

        $vehicle1 = Vehicle::create([
            'name' => 'Honda Vision 2023',
            'license' => '29B1-12345',
            'type' => 'xega',
        ]);

        $vehicle2 = Vehicle::create([
            'name' => 'Yamaha Exciter',
            'license' => '29B1-67890',
            'type' => 'xecon',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'total' => 900000,
            'first_deposit_amount' => 1000000,
            'additional_deposit_amount' => 500000,
            'contract_collateral_description' => 'CCCD + 1.500.000đ tiền mặt',
            'order_status' => OrderValidator::ORDER_RENTING,
            'contract_number' => '2026/09/14-0010',
            'contract_signed_on' => '2026-09-14',
        ]);

        // Item 1: Day rental
        OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle1->id,
            'type' => 'day',
            'substitute_unit_price' => 150000,
            'total_money' => 450000,
            'borrow_hats' => 2,
            'borrow_raincoats' => 1,
            'rent_at' => '2026-09-14 08:00:00',
            'return_at' => '2026-09-17 08:00:00',
        ]);

        // Item 2: Package rental
        OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle2->id,
            'type' => 'total',
            'handler_price' => 450000,
            'total_money' => 450000,
            'borrow_hats' => 1,
            'borrow_raincoats' => 1,
            'rent_at' => '2026-09-14 08:00:00',
            'return_at' => '2026-09-16 20:00:00',
        ]);

        // Transactions
        Transaction::create([
            'name' => 'order:deposit:' . $order->id,
            'type' => 'in',
            'value' => 1000000,
            'payment_method' => 1, // cash
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);

        $this->assertNotNull($snapshot);
        $this->assertEquals('2026/09/14-0010', $snapshot['contract_number']);
        $this->assertCount(2, $snapshot['vehicles']);

        // Item 1: day pricing mode
        $this->assertEquals('day', $snapshot['vehicles'][0]['pricing_mode']);
        $this->assertEquals(150000, $snapshot['vehicles'][0]['unit_price']);

        // Item 2: package pricing mode
        $this->assertEquals('package', $snapshot['vehicles'][1]['pricing_mode']);
        $this->assertEquals(450000, $snapshot['vehicles'][1]['unit_price']);

        // Total accessories
        $this->assertEquals(3, $snapshot['accessories']['total_hats']);
        $this->assertEquals(2, $snapshot['accessories']['total_raincoats']);

        // Payment summary
        $this->assertEquals(1500000, $snapshot['payment']['total_deposit']);
        $this->assertCount(1, $snapshot['payment']['transactions_summary']);
        $this->assertEquals(1000000, $snapshot['payment']['transactions_summary'][0]['value']);
    }

    /**
     * Test Round 2 - Issue 1: Re-submitting return confirmation on unpaid order does not increase total.
     */
    public function testReturnConfirmationIdempotencyDoesNotIncreaseOrderTotal()
    {
        $customer = Customer::create(['name' => 'Khách A', 'id_card' => '001234567890']);
        $vehicle = Vehicle::create(['name' => 'Xe 01', 'license' => '29A-11111']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 500000,
            'outdate_or_early_amount' => 0,
            'contract_number' => '2026/09/14-0001',
            'contract_snapshot' => ['customer' => ['name' => 'Khách A']],
        ]);
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $payload = [
            'order_items' => [
                [
                    'id' => $item->id,
                    'money_out_date' => 50000,
                    'minute_out_date' => 60,
                ],
            ],
            'completed_at' => '14-09-2026 15:00:00',
            'isPaid' => false,
            'total_refund_amount' => 0,
            'return_signer_a_name' => 'NV Tiếp Nhận',
            'return_signer_b_name' => 'Khách A',
            'return_additional_note' => 'Trả xe chưa thanh toán',
        ];

        // First complete call
        $this->orderService->complete(new Request($payload), $order);
        $order->refresh();
        $this->assertEquals(550000, (float) $order->total, 'First complete should set total to 550,000');
        $this->assertEquals(50000, (float) $order->outdate_or_early_amount);
        $this->assertEquals(OrderValidator::ORDER_UNPAID, $order->order_status);

        // Second complete call with exact same data (retry or repeat confirm)
        $this->orderService->complete(new Request($payload), $order);
        $order->refresh();
        $this->assertEquals(550000, (float) $order->total, 'Repeated complete MUST NOT increase total to 600,000');
        $this->assertEquals(50000, (float) $order->outdate_or_early_amount);

        // Third call adjusting fee from 50k to 70k
        $payload['order_items'][0]['money_out_date'] = 70000;
        $this->orderService->complete(new Request($payload), $order);
        $order->refresh();
        $this->assertEquals(570000, (float) $order->total, 'Delta adjustment should update total to 570,000');
        $this->assertEquals(70000, (float) $order->outdate_or_early_amount);
    }

    /**
     * Test Round 2 - Issue 2: Multi-day rental without substitute price calculates daily unit price.
     */
    public function testMultiDayRentalWithoutSubstitutePriceCalculatesDailyUnitPrice()
    {
        $customer = Customer::create(['name' => 'Khách B', 'id_card' => '001234567891']);
        $vehicle = Vehicle::create(['name' => 'Xe Ga 02', 'license' => '29B-22222', 'type' => 'xega']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 450000,
            'contract_number' => '2026/09/14-0002',
            'contract_signed_on' => '2026-09-14',
        ]);

        // 3 days rental: 14/09 09:00 -> 17/09 09:00, total 450,000
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'day',
            'rent_at' => '2026-09-14 09:00:00',
            'return_at' => '2026-09-17 09:00:00',
            'total_money' => 450000,
            'hiring_fee' => 450000,
            'handler_price' => 0,
            'substitute_unit_price' => 0,
        ]);

        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);

        $this->assertNotNull($snapshot);
        $this->assertEquals('day', $snapshot['vehicles'][0]['pricing_mode']);
        $this->assertEquals('ngày', $snapshot['vehicles'][0]['pricing_unit']);
        $this->assertEquals(150000, $snapshot['vehicles'][0]['unit_price'], 'Daily unit price must be 150,000/day, not the 450,000 total');
    }

    /**
     * Test Round 2 - Issue 2: Handler price forces package pricing mode even if type is day.
     */
    public function testHandlerPriceForcesPackagePricingModeEvenIfTypeIsDay()
    {
        $customer = Customer::create(['name' => 'Khách C', 'id_card' => '001234567892']);
        $vehicle = Vehicle::create(['name' => 'Xe 03', 'license' => '29C-33333']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 400000,
            'contract_number' => '2026/09/14-0003',
            'contract_signed_on' => '2026-09-14',
        ]);

        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'day',
            'rent_at' => '2026-09-14 09:00:00',
            'return_at' => '2026-09-17 09:00:00',
            'total_money' => 400000,
            'hiring_fee' => 400000,
            'handler_price' => 400000,
            'substitute_unit_price' => 0,
        ]);

        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);

        $this->assertEquals('package', $snapshot['vehicles'][0]['pricing_mode']);
        $this->assertEquals('gói', $snapshot['vehicles'][0]['pricing_unit']);
        $this->assertEquals(400000, $snapshot['vehicles'][0]['unit_price']);
    }

    /**
     * Test Round 2 - Issue 3: Snapshot is updated during renting state when customer information is filled later.
     */
    public function testSnapshotUpdatedDuringRentingStateWhenCustomerInfoFilledLater()
    {
        $customer = Customer::create([
            'name' => 'Khách D',
            'id_card' => '001234567893',
            'id_card_issued_by' => null,
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'contract_number' => '2026/09/14-0004',
            'contract_signed_on' => '2026-09-14',
        ]);

        // Initial snapshot generated when ID card issuer was missing
        $initialSnapshot = $this->orderService->maybeGenerateContractSnapshot($order);
        $this->assertNull($initialSnapshot['customer']['id_card_issued_by']);

        // Later, customer updates ID card issuer
        $customer->update(['id_card_issued_by' => 'Cục Cảnh Sát QLHC']);

        // Call maybeGenerateContractSnapshot again on active order
        $updatedSnapshot = $this->orderService->maybeGenerateContractSnapshot($order->fresh());
        $this->assertEquals('Cục Cảnh Sát QLHC', $updatedSnapshot['customer']['id_card_issued_by']);
        $this->assertEquals('2026/09/14-0004', $updatedSnapshot['contract_number'], 'Contract number must be preserved');
    }

    /**
     * Test Round 2 - Issue 3: Snapshot is frozen once order is completed or unpaid.
     */
    public function testSnapshotFrozenOnceOrderIsCompletedOrUnpaid()
    {
        $customer = Customer::create([
            'name' => 'Khách E Gốc',
            'id_card' => '001234567894',
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_COMPLETED,
            'contract_number' => '2026/09/14-0005',
            'contract_signed_on' => '2026-09-14',
            'contract_snapshot' => [
                'is_locked' => true,
                'contract_number' => '2026/09/14-0005',
                'customer' => ['name' => 'Khách E Gốc'],
            ],
        ]);

        // Even if customer name in DB changes later:
        $customer->update(['name' => 'Khách E Đã Đổi']);

        $result = $this->orderService->maybeGenerateContractSnapshot($order->fresh());
        $this->assertEquals('Khách E Gốc', $result['customer']['name'], 'Snapshot must remain frozen on closed order');
    }

    /**
     * Test Round 2 - Issue 4: Actual staff return time is recorded in order and snapshot, not processing now().
     */
    public function testActualReturnTimePreservedInOrderAndSnapshot()
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 19:00:00', 'Asia/Ho_Chi_Minh'));

        $customer = Customer::create(['name' => 'Khách F', 'id_card' => '001234567895']);
        $vehicle = Vehicle::create(['name' => 'Xe 06', 'license' => '29F-66666']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 300000,
            'contract_snapshot' => ['customer' => ['name' => 'Khách F']],
        ]);
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $payload = [
            'order_items' => [
                ['id' => $item->id, 'money_out_date' => 0, 'minute_out_date' => 0],
            ],
            'completed_at' => '14-09-2026 15:00:00', // Actual return time entered by staff
            'isPaid' => false,
            'return_signer_a_name' => 'NV Tiếp Nhận',
            'return_signer_b_name' => 'Khách F',
            'return_additional_note' => 'Trả xe đúng hẹn',
        ];

        $this->orderService->complete(new Request($payload), $order);
        $order->refresh();

        $this->assertEquals('2026-09-14 15:00:00', Carbon::parse($order->completed_at)->format('Y-m-d H:i:s'), 'Order completed_at must match input 15:00');
        $this->assertEquals('14/09/2026 15:00:00', $order->contract_snapshot['return_confirmation']['completed_at'], 'Snapshot return confirmation completed_at must match input 15:00, not 19:00');

        Carbon::setTestNow();
    }

    /**
     * Test Round 2 - Issue 6: CCCD issued on is formatted as ISO date in snapshot.
     */
    public function testCccdIssuedOnFormatInSnapshotIsIsoDate()
    {
        $customer = Customer::create([
            'name' => 'Khách G',
            'id_card' => '001234567896',
            'id_card_issued_on' => '2020-01-02',
            'id_card_issued_by' => 'Cục CS QLHC',
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'contract_number' => '2026/09/14-0007',
            'contract_signed_on' => '2026-09-14',
        ]);

        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);

        $this->assertEquals('2020-01-02', $snapshot['customer']['id_card_issued_on'], 'id_card_issued_on in snapshot must be ISO date YYYY-MM-DD');
        $this->assertEquals('2026-09-14', $snapshot['signed_on'], 'signed_on in snapshot must be ISO date YYYY-MM-DD');
    }

    /**
     * Test Round 3 - Issue 1: Previewing late fees via calc_order_before_complete writes outdate_or_early_amount
     * while total remains unchanged; complete() then adds the late fees correctly, and retrying is idempotent.
     */
    public function testRound3PreviewThenCompleteSettlementIncludesOverdueFee()
    {
        $customer = Customer::create(['name' => 'Khách H', 'id_card' => '001234567897']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 500000,
            'return_adjustment_applied' => 0,
            'contract_snapshot' => ['customer' => ['name' => 'Khách H']],
        ]);
        $vehicle = Vehicle::create(['name' => 'Xe Côn Winner', 'type' => 'xecon']);
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'rent_at' => '2026-09-13 13:00:00',
            'return_at' => '2026-09-14 13:00:00',
            'type' => 'day',
            'substitute_unit_price' => 500000,
            'total_money' => 500000,
        ]);

        // Preview return at 15:00 (2 hours late => 2 * 25,000 = 50,000)
        $previewRequest = new Request(['completed_at' => '14-09-2026 15:00:00']);
        CarRentalHelper::writeMoneyOutDateAndTotal($order, $previewRequest);
        CarRentalHelper::whenOrderReturnEarly($order, $previewRequest);
        $order->refresh();
        $item->refresh();

        $this->assertEquals(500000, (float) $order->total, 'Preview must keep order total unchanged');
        $this->assertEquals(50000, (float) $order->outdate_or_early_amount, 'Preview writes preview adjustment to outdate_or_early_amount');
        $this->assertEquals(0, (float) $order->return_adjustment_applied, 'Preview does not mark return_adjustment_applied as applied');

        // Complete the return (unpaid / wait_payment)
        $completePayload = [
            'completed_at' => '14-09-2026 15:00:00',
            'isPaid' => false,
            'order_items' => [
                [
                    'id' => $item->id,
                    'money_out_date' => $item->money_out_date,
                    'minute_out_date' => $item->minute_out_date,
                    'order_item_fees' => [],
                ],
            ],
        ];
        $this->orderService->complete(new Request($completePayload), $order);
        $order->refresh();

        $this->assertEquals(550000, (float) $order->total, 'Total must include the 50,000 overdue fee after complete');
        $this->assertEquals(50000, (float) $order->return_adjustment_applied, 'return_adjustment_applied tracks applied fee');

        // Retry complete - idempotent check
        $this->orderService->complete(new Request($completePayload), $order);
        $order->refresh();
        $this->assertEquals(550000, (float) $order->total, 'Retry must not re-add overdue fee to total');
    }

    /**
     * Test Round 3 - Issue 2: PriceVehicle band matching by rental days and vehicle year in getUnitPrice and snapshot.
     */
    public function testRound3CatalogMultiBandTierPriceSelection()
    {
        PriceVehicle::create([
            'type' => 'xega',
            'price_type' => 'day',
            'from_year' => 2020,
            'to_year' => 2026,
            'from_date' => 1,
            'to_date' => 1,
            'price' => 200000,
        ]);
        PriceVehicle::create([
            'type' => 'xega',
            'price_type' => 'day',
            'from_year' => 2020,
            'to_year' => 2026,
            'from_date' => 2,
            'to_date' => 5,
            'price' => 150000,
        ]);

        $customer = Customer::create(['name' => 'Khách I', 'id_card' => '001234567898']);
        $vehicle = Vehicle::create(['name' => 'AirBlade 2023', 'type' => 'xega', 'year' => 2023]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'contract_number' => '2026/09/14-0008',
            'contract_signed_on' => '2026-09-14',
            'total' => 450000,
        ]);
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'day',
            'rent_at' => '2026-09-14 09:00:00',
            'return_at' => '2026-09-17 09:00:00', // 3 days
            'total_money' => 450000,
            'hiring_fee' => 450000,
            'substitute_unit_price' => 0,
            'handler_price' => 0,
        ]);

        $unitPrice = CarRentalHelper::getUnitPrice($item);
        $this->assertEquals(150000, (float) $unitPrice, 'Unit price for 3-day rental must match 2-5 days tier (150k), not 1 day (200k)');

        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order);
        $this->assertEquals(150000, (float) $snapshot['vehicles'][0]['unit_price'], 'Snapshot unit price must take the 150k tier');
    }

    /**
     * Test Round 3 - Issue 3: Explicit lock contract step freezes the signed contract snapshot
     * and prevents subsequent updates to signed customer/contract info.
     */
    public function testRound3ContractLockPreventsSignedSnapshotMutation()
    {
        $customer = Customer::create(['name' => 'Khách K', 'id_card' => '001234567899']);
        $vehicle = Vehicle::create(['name' => 'Lead', 'type' => 'xega']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'contract_number' => '2026/09/14-0009',
            'contract_signed_on' => '2026-09-14',
            'total' => 300000,
        ]);
        $item = OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'day',
            'rent_at' => '2026-09-14 09:00:00',
            'return_at' => '2026-09-16 09:00:00',
            'total_money' => 300000,
            'substitute_unit_price' => 150000,
        ]);

        // Before locking, updating customer updates snapshot
        $this->orderService->maybeGenerateContractSnapshot($order);
        $customer->update(['id_card_issued_by' => 'Công an TP Hà Nội']);
        $snapshot = $this->orderService->maybeGenerateContractSnapshot($order->fresh());
        $this->assertEquals('Công an TP Hà Nội', $snapshot['customer']['id_card_issued_by']);

        // Staff locks the signed contract
        $this->orderService->lockContract($order);
        $order->refresh();
        $this->assertTrue((bool) data_get($order->contract_snapshot, 'is_locked'), 'Snapshot is_locked must be true after lockContract');

        // Customer changed while renting after lock
        $customer->update(['name' => 'Tên bị đổi sau khi ký']);
        $snapshotAfter = $this->orderService->maybeGenerateContractSnapshot($order->fresh());
        $this->assertEquals('Khách K', $snapshotAfter['customer']['name'], 'Signed contract snapshot customer name must be frozen and NOT mutated');

        // Attempting to call update() on locked contract must throw ValidationException
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->update(new Request(['store_id' => 1, 'customer_name' => 'Tên mới']), $order->fresh());
    }

    /**
     * Test Round 3 - Issue 5: Completing a legacy order without contract number leaves
     * contract_number as null in both database and snapshot; snapshot does not mint a phantom number.
     */
    public function testRound3LegacyOrderReturnLeavesContractNumberNull()
    {
        $customer = Customer::create(['name' => 'Khách L', 'id_card' => '001234567888']);
        $legacy = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 0,
            'contract_number' => null,
        ]);

        $this->orderService->complete(new Request([
            'completed_at' => '14-09-2026 15:00:00',
            'isPaid' => false,
            'order_items' => [],
        ]), $legacy);
        $legacy->refresh();

        $this->assertNull($legacy->contract_number, 'orders.contract_number must remain null for legacy order');
        $this->assertNull($legacy->contract_snapshot['contract_number'], 'contract_snapshot.contract_number must remain null');
    }

    /**
     * Test Round 3 - Issue 5 regression: Searching by contract number finds legacy orders
     * where contract_number column in orders is null but stored in contract_snapshot JSON.
     * OrderResource also falls back to contract_snapshot.contract_number so column is not empty.
     */
    public function testRound3KeywordSearchFindsLegacyOrderWithContractNumberInSnapshot()
    {
        foreach (['add_on_orders', 'leads', 'activity_logs'] as $table) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function ($t) {
                    $t->increments('id');
                    $t->integer('order_id')->nullable();
                    $t->timestamps();
                });
            }
        }

        $customer = Customer::create(['name' => 'Khách M', 'id_card' => '001234567777']);
        $legacyOrder = Order::create([
            'customer_id' => $customer->id,
            'order_status' => OrderValidator::ORDER_RENTING,
            'total' => 350000,
            'contract_number' => null, // Empty DB column
            'contract_snapshot' => [
                'contract_number' => '2026/09/14-9999',
                'customer' => ['name' => 'Khách M'],
            ],
        ]);

        // Search using repository applyKeywordFilter by the contract number in snapshot
        $query = Order::query();
        $this->orderRepository->applyKeywordFilter($query, '2026/09/14-9999');
        $results = $query->get();

        $this->assertTrue($results->contains('id', $legacyOrder->id), 'Search must find legacy order by contract_number inside contract_snapshot');

        // Verify OrderResource maps contract_number from snapshot so frontend column is not empty
        $resourceData = (new \App\Http\Resources\OrderResource($legacyOrder))->toArray(request());
        $this->assertEquals('2026/09/14-9999', $resourceData['contract_number'], 'OrderResource must fall back to contract_number in snapshot');
    }
}

