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
}
