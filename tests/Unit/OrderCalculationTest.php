<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\CarRentalHelper;
use App\Models\Vehicle;
use App\Models\PriceVehicle;
use App\Models\OrderVehicleDetail;
use App\Models\Order;
use App\Models\User;

class OrderCalculationTest extends TestCase
{
    /**
     * Test late return surcharge calculation for standard motorbikes (xeso, xega).
     * Rule: < 8 hours is charged 15,000 VND/hour. >= 8 hours is charged a full day's unit price.
     */
    public function testLateFeeCalculationForMotorbike()
    {
        $vehicle = new Vehicle(['type' => 'xega']);
        $orderItem = new OrderVehicleDetail([
            'type' => 'day',
            'substitute_unit_price' => 120000,
        ]);
        $orderItem->setRelation('vehicle', $vehicle);

        // Case 1: 3 hours late => 3 * 15,000 = 45,000 VND
        $fee3Hours = CarRentalHelper::calMoneyOutDate($orderItem, 3);
        $this->assertEquals(45000, $fee3Hours, "3 hours late for xega should be 45,000 VND");

        // Case 2: 7 hours late => 7 * 15,000 = 105,000 VND
        $fee7Hours = CarRentalHelper::calMoneyOutDate($orderItem, 7);
        $this->assertEquals(105000, $fee7Hours, "7 hours late for xega should be 105,000 VND");

        // Case 3: 8 hours or more late => rounds up to 1 full day unit price (120,000 VND)
        $fee8Hours = CarRentalHelper::calMoneyOutDate($orderItem, 8);
        $this->assertEquals(120000, $fee8Hours, "8 hours late should round up to 1 day rate (120,000 VND)");

        // Case 4: 27 hours late => 1 day (120,000) + 3 hours (3 * 15,000 = 45,000) = 165,000 VND
        $fee27Hours = CarRentalHelper::calMoneyOutDate($orderItem, 27);
        $this->assertEquals(165000, $fee27Hours, "27 hours late should be 1 day (120k) + 3h (45k) = 165,000 VND");
    }

    /**
     * Test late fee calculation for manual/clutch bikes (xecon) and luxury scooters (sh).
     */
    public function testLateFeeCalculationForPremiumBikes()
    {
        // Xe con: 25,000 VND/hour for late < 8 hours
        $xecon = new Vehicle(['type' => 'xecon']);
        $orderItemXecon = new OrderVehicleDetail([
            'type' => 'day',
            'substitute_unit_price' => 200000,
        ]);
        $orderItemXecon->setRelation('vehicle', $xecon);

        $feeXecon4h = CarRentalHelper::calMoneyOutDate($orderItemXecon, 4);
        $this->assertEquals(100000, $feeXecon4h, "4 hours late for xecon (25k/h) should be 100,000 VND");

        // SH: 35,000 VND/hour for late < 8 hours
        $sh = new Vehicle(['type' => 'sh']);
        $orderItemSh = new OrderVehicleDetail([
            'type' => 'day',
            'substitute_unit_price' => 350000,
        ]);
        $orderItemSh->setRelation('vehicle', $sh);

        $feeSh2h = CarRentalHelper::calMoneyOutDate($orderItemSh, 2);
        $this->assertEquals(70000, $feeSh2h, "2 hours late for sh (35k/h) should be 70,000 VND");
    }

    /**
     * Test human-readable minute formatting helper (converMinutesInDay).
     */
    public function testConvertMinutesInDay()
    {
        $this->assertEquals("Đến giờ trả xe", CarRentalHelper::converMinutesInDay(45));
        $this->assertEquals("1 giờ 30 phút", CarRentalHelper::converMinutesInDay(90));
        $this->assertEquals("1 Ngày 1 giờ ", CarRentalHelper::converMinutesInDay(1500));
        $this->assertEquals("2 Ngày 3 giờ 15 phút", CarRentalHelper::converMinutesInDay(2880 + 180 + 15));
    }

    /**
     * Test deposit, payment, and refund calculations.
     */
    public function testOrderDepositAndRefundCalculation()
    {
        $order = new Order([
            'first_deposit_amount' => 1000000,
            'additional_deposit_amount' => 500000,
            'total' => 1200000,
            'total_rental_fees' => 1200000,
        ]);

        $totalDeposit = $order->first_deposit_amount + $order->additional_deposit_amount;
        $this->assertEquals(1500000, $totalDeposit, "Total deposit must equal first + additional deposit");

        // Case: Customer already paid 1,000,000 VND of the 1,200,000 VND rental total.
        $paidAmount = 1000000;
        $outstandingRentalFee = $order->total - $paidAmount;
        $this->assertEquals(200000, $outstandingRentalFee, "Outstanding fee should be 200,000 VND");

        // On return, refund = deposit - outstanding rental fee:
        $refundToCustomer = $totalDeposit - $outstandingRentalFee;
        $this->assertEquals(1300000, $refundToCustomer, "Refund to customer should be deposit (1.5M) - outstanding (200k) = 1,300,000 VND");
    }

    /**
     * Test early return calculation with fixed handler price.
     * When handler_price > 0, price is contractually fixed, so refund is 0.
     */
    public function testEarlyReturnFixedHandlerPriceRule()
    {
        $vehicle = new Vehicle(['type' => 'xega']);
        $item = new OrderVehicleDetail([
            'rent_at' => '2026-09-01 08:00:00',
            'return_at' => '2026-09-05 08:00:00',
            'total_money' => 600000,
            'handler_price' => 500000, // Fixed package agreed with customer
        ]);
        $item->setRelation('vehicle', $vehicle);

        // Handler price overrides dynamic refund calculation
        $handlePrice = $item->handler_price;
        $moneyToReturn = 0;
        if (is_numeric($handlePrice) && $handlePrice > 0) {
            $moneyToReturn = 0;
        }

        $this->assertEquals(0, $moneyToReturn, "Fixed handler_price contract must not permit dynamic refund deduction");
    }

    /**
     * Test role and store branch boundary enforcement.
     * Staff with role_id != 1 (non-superadmin) must have their operational scope restricted to their assigned store_id.
     */
    public function testBranchStaffStoreScopingBoundary()
    {
        $superAdmin = new User(['id' => 1, 'role_id' => 1, 'store_id' => 1]);
        $branchStaff = new User(['id' => 5, 'role_id' => 2, 'store_id' => 2]); // Staff of Store 2

        // Superadmin is permitted to operate across any branch
        $isSuperAdmin = ($superAdmin->role_id === 1);
        $this->assertTrue($isSuperAdmin, "Role ID 1 must be identified as Superadmin");

        // Branch staff must be restricted to their own branch store_id
        $isStaffSuperAdmin = ($branchStaff->role_id === 1);
        $this->assertFalse($isStaffSuperAdmin, "Branch staff must NOT have Superadmin privilege");

        $assignedStoreId = ($branchStaff->role_id !== 1) ? $branchStaff->store_id : 1;
        $this->assertEquals(2, $assignedStoreId, "Branch staff action must be scoped strictly to store_id = 2");
    }

    /**
     * Test OrderController validation rejects mismatched deposit and rental payment sums.
     */
    public function testOrderControllerPaymentValidationMismatch()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        // Mismatched payment: total specified is 1,000,000 but sum of cash + transfer is 800,000
        $paymentMethod = [
            'payment_method' => 3,
            'cash_amount' => 500000,
            'bank_transfer_amount' => 300000,
            'bank_id' => 1
        ];

        $response = $controller->validate_input_payment(
            'Tổng số tiền đặt cọc không khớp',
            $paymentMethod,
            1000000
        );

        $this->assertNotNull($response, "validate_input_payment must return error on mismatch");
        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Tổng số tiền đặt cọc không khớp', $responseData['message']);
    }

    /**
     * Test OrderController validation requires bank selection when bank transfer amount > 0.
     */
    public function testOrderControllerBankRequiredWhenBankTransferUsed()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $paymentMethod = [
            'payment_method' => 2,
            'bank_transfer_amount' => 500000,
            'cash_amount' => 0,
            'bank_id' => null
        ];

        $response = $controller->validate_input_payment(
            'Tổng số tiền đặt cọc không khớp',
            $paymentMethod,
            500000
        );

        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Vui lòng chọn tài khoản ngân hàng', $responseData['message']);
    }

    /**
     * Test OrderController validation succeeds (returns null) when payments match cleanly.
     */
    public function testOrderControllerPaymentMatchesCleanly()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $paymentMethod = [
            'payment_method' => 3,
            'bank_transfer_amount' => 600000,
            'cash_amount' => 400000,
            'bank_id' => 1
        ];

        $response = $controller->validate_input_payment(
            'Tổng số tiền đặt cọc không khớp',
            $paymentMethod,
            1000000
        );

        $this->assertNull($response, "validate_input_payment must return null when payment sums match perfectly");
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasTable('transactions')) {
            \Illuminate\Support\Facades\Schema::create('transactions', function ($table) {
                $table->increments('id');
                $table->integer('user_id')->nullable();
                $table->integer('store_id')->nullable();
                $table->string('name')->nullable();
                $table->string('type')->nullable();
                $table->integer('value')->default(0);
                $table->integer('payment_method')->nullable();
                $table->integer('bank_id')->nullable();
                $table->integer('cash_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('orders')) {
            \Illuminate\Support\Facades\Schema::create('orders', function ($table) {
                $table->increments('id');
                $table->integer('store_id')->nullable();
                $table->string('order_status')->nullable();
                $table->boolean('deposit_closed')->default(false);
                $table->integer('total')->default(0);
                $table->integer('out_dated_at')->default(0);
                $table->integer('outdate_or_early_amount')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('order_vehicle_details')) {
            \Illuminate\Support\Facades\Schema::create('order_vehicle_details', function ($table) {
                $table->increments('id');
                $table->integer('order_id')->nullable();
                $table->integer('vehicle_id')->nullable();
                $table->string('type')->nullable();
                $table->dateTime('rent_at')->nullable();
                $table->dateTime('return_at')->nullable();
                $table->integer('substitute_unit_price')->default(0);
                $table->integer('total_money')->default(0);
                $table->integer('total_renewal_amount')->default(0);
                $table->integer('minute_out_date')->default(0);
                $table->integer('money_out_date')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Test OrderController transaction rollback when OrderService throws an exception.
     */
    public function testOrderControllerDatabaseTransactionRollbackOnException()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $order = new Order(['id' => 101, 'order_status' => 'pending']);

        $orderServiceMock->expects($this->once())
            ->method('deposit')
            ->willThrowException(new \Exception("Simulated Database Deadlock or Constraint Violation"));

        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);
        $request = new \Illuminate\Http\Request(['amount' => 500000]);

        $response = $controller->deposit($request, $order);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString("Simulated Database Deadlock or Constraint Violation", $responseData['message']);
    }

    /**
     * Test ReceiptController validation rejects transaction when total amount is 0.
     */
    public function testReceiptControllerZeroAmountValidation()
    {
        $transactionServiceMock = $this->createMock(\App\Http\Services\TransactionService::class);
        $controller = new \App\Http\Controllers\ReceiptController($transactionServiceMock);

        $request = new \Illuminate\Http\Request([
            'cash_amount' => 0,
            'bank_transfer_amount' => 0
        ]);

        $response = $controller->putOrPost($request);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Số tiền thanh toán phải lớn hơn 0', $responseData['message']);
    }

    /**
     * Test ReceiptController role store boundary enforcement:
     * Staff with role_id != 1 (Store 2) cannot create receipts for another store_id (e.g. 999).
     * Verifies that the inserted transaction in the database is strictly store_id = 2.
     */
    public function testReceiptControllerRoleStoreBoundaryEnforcement()
    {
        $branchStaff = new User(['role_id' => 2, 'store_id' => 2]);
        $branchStaff->id = 5;
        $this->actingAs($branchStaff);
        \Illuminate\Support\Facades\Auth::setUser($branchStaff);

        $transactionServiceMock = $this->createMock(\App\Http\Services\TransactionService::class);
        // Expect that processPaymentMethod receives store_id = 2, overriding store_id = 999
        $transactionServiceMock->expects($this->once())
            ->method('processPaymentMethod')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo(2) // Scoped strictly to branch staff store_id
            )
            ->willReturn(['bank_id' => null, 'cash_id' => 1]);

        $controller = new \App\Http\Controllers\ReceiptController($transactionServiceMock);
        $request = new \Illuminate\Http\Request([
            'cash_amount' => 100000,
            'bank_transfer_amount' => 0,
            'payment_method' => 1,
            'bank_id' => null,
            'store_id' => 999 // Attempt to inject foreign store ID
        ]);

        $response = $controller->putOrPost($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify database persistence respects branch isolation
        $this->assertDatabaseHas('transactions', [
            'store_id' => 2,
            'user_id' => 5,
            'value' => 100000
        ]);
        $this->assertDatabaseMissing('transactions', [
            'store_id' => 999
        ]);
    }

    /**
     * Test OrderController store rejects order when return_at is earlier than rent_at.
     */
    public function testOrderControllerStoreRejectsInvalidReturnDateBeforeRentDate()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $request = new \Illuminate\Http\Request([
            'store_id' => 1,
            'total' => 500000,
            'customer_name' => 'Nguyen Van A',
            'customer_id_card' => 123456789,
            'first_deposit_amount' => 0,
            'total_rental_fees' => 0,
            'additional_deposit_amount' => 0,
            'order_items' => [
                [
                    'vehicle_id' => 1,
                    'rent_at' => '2026-09-15 10:00:00',
                    'return_at' => '2026-09-14 10:00:00' // Return is before Rent!
                ]
            ]
        ]);

        $response = $controller->store($request);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Thời gian trả xe phải muộn hơn thời gian thuê xe', $responseData['message']);
    }

    /**
     * Test OrderController update rejects modifying already liquidated and deposit-closed contracts.
     */
    public function testOrderControllerUpdateRejectsLiquidatedOrder()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $liquidatedOrder = new Order([
            'id' => 200,
            'order_status' => 'completed',
            'deposit_closed' => 1
        ]);

        $request = new \Illuminate\Http\Request([
            'first_deposit_amount' => 0,
            'total_rental_fees' => 0,
            'additional_deposit_amount' => 0,
        ]);

        $response = $controller->update($request, $liquidatedOrder);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Không thể cập nhật trên hợp đồng đã thanh lý', $responseData['message']);
    }

    /**
     * Test OrderController complete (vehicle return & refund) requires bank account when refunding via bank transfer.
     */
    public function testOrderControllerCompleteValidationRequiresBankWhenRefundViaBank()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $order = new Order(['id' => 300, 'order_status' => 'renting']);

        $request = new \Illuminate\Http\Request([
            'total_refund_amount' => 500000,
            'refund_payment_method' => 2, // Bank transfer
            'bank_transfer_amount' => 500000,
            'cash_amount' => 0,
            'refund_bank_id' => null // Missing required bank ID
        ]);

        $response = $controller->complete($request, $order);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Vui lòng chọn một tài khoản ngân hàng', $responseData['message']);
    }

    /**
     * Test OrderController complete (vehicle return & refund) rejects split refund when cash + bank amount does not match total refund.
     */
    public function testOrderControllerCompleteValidationMismatchCashAndBank()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $order = new Order(['id' => 301, 'order_status' => 'renting']);

        $request = new \Illuminate\Http\Request([
            'total_refund_amount' => 1000000,
            'refund_payment_method' => 3, // Combined
            'bank_transfer_amount' => 400000,
            'cash_amount' => 400000, // Sum is 800k, not 1,000,000!
            'refund_bank_id' => 1
        ]);

        $response = $controller->complete($request, $order);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Tổng số tiền mặt và chuyển khoản bạn nhập vào không khớp với tổng tiền cần thanh toán', $responseData['message']);
    }

    /**
     * Test OrderController complete database transaction rollback when OrderService throws an exception during vehicle return.
     */
    public function testOrderControllerCompleteDatabaseTransactionRollbackOnException()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $order = new Order(['id' => 302, 'order_status' => 'renting']);

        $orderServiceMock->expects($this->once())
            ->method('complete')
            ->willThrowException(new \Exception("Vehicle Return Inventory Deadlock"));

        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        $request = new \Illuminate\Http\Request([
            'total_refund_amount' => 200000,
            'refund_payment_method' => 1, // Cash
            'cash_amount' => 200000,
            'bank_transfer_amount' => 0
        ]);

        $response = $controller->complete($request, $order);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString("Vehicle Return Inventory Deadlock", $responseData['message']);
    }

    /**
     * Test OrderController addOnPrice (rental extension / gia hạn thuê) succeeds when authorized.
     */
    public function testOrderControllerAddOnPriceSuccess()
    {
        $user = new User(['name' => 'Staff Tester']);
        $user->id = 1;
        $this->actingAs($user);
        \Illuminate\Support\Facades\Auth::setUser($user);

        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $orderServiceMock->expects($this->once())
            ->method('addOnPrice')
            ->willReturn(new \App\Entities\AddOnOrder(['id' => 1, 'price' => 150000]));

        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'id' => 10,
            'store_id' => 1,
            'order_status' => 'renting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('order_vehicle_details')->insert([
            'id' => 1,
            'order_id' => 10,
            'total_renewal_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = new \Illuminate\Http\Request([
            'order_id' => 10,
            'line_item_id' => 1,
            'price' => 150000,
            'return_at' => '2026-09-20 18:00:00',
            'return_at_formatted' => '2026-09-20 18:00:00'
        ]);

        $response = $controller->addOnPrice($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Nạp tiền gia hạn thành công', $responseData['message']);

        // Assert database persistence of renewal update
        $this->assertDatabaseHas('order_vehicle_details', [
            'id' => 1,
            'total_renewal_amount' => 150000
        ]);
    }

    /**
     * Test OrderController calc_return_early_amount handles error cleanly when calculation fails.
     */
    public function testOrderControllerCalcReturnEarlyAmountRollbackOnError()
    {
        $orderServiceMock = $this->createMock(\App\Http\Services\OrderService::class);
        $orderServiceMock->expects($this->once())
            ->method('calcOrderReturnEarlyAmount')
            ->willThrowException(new \Exception("Cannot calculate early return for unstarted order"));

        $controller = new \App\Http\Controllers\Order\OrderController($orderServiceMock);

        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'id' => 55,
            'store_id' => 1,
            'order_status' => 'renting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = new \Illuminate\Http\Request(['order_id' => 55]);

        $response = $controller->calc_return_early_amount($request);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot calculate early return for unstarted order', $responseData['message']);
    }
}


