<?php

namespace Tests\Unit;

require_once __DIR__ . '/HimotoCashRegisterAndHrTest.php';

use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\LeasePaymentAllocation;
use App\Models\Transaction;
use App\Models\DailyCashRegister;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Vehicle;
use App\Models\Store;
use App\Models\StaffProfile;
use App\Models\StoreDutySchedule;
use App\Models\Cash;
use App\Models\VehicleLocationEvent;
use App\Http\Services\VehicleTransferService;
use App\Http\Controllers\DailyCashRegisterController;
use App\Http\Controllers\HrController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewDefectFixesTest extends HimotoCashRegisterAndHrTest
{
    /**
     * R02: Zero VND settlement must be rejected when debt remains;
     * Real settlement must allocate transactions and only complete when fully paid.
     */
    public function testR02ZeroSettlementRejectedAndRealSettlementAllocates()
    {
        $this->testLeaseContractEarlySettlement();
        $contract = LeaseContract::first();
        DB::table('lease_payment_allocations')->delete();
        DB::table('transactions')->delete();
        DB::table('lease_installments')->update(['amount_paid' => 0, 'status' => 'unpaid']);
        $contract->update(['status' => 'active', 'discount_amount' => 0, 'settled_at' => null]);

        // 1. Zero settlement must fail
        $exceptionCaught = false;
        try {
            $this->leaseService->settleContract($contract->id, ['settlement_amount' => 0], $this->adminUser);
        } catch (ValidationException $e) {
            $exceptionCaught = true;
            $this->assertArrayHasKey('settlement_amount', $e->errors());
        }
        $this->assertTrue($exceptionCaught, 'Zero settlement must throw ValidationException when debt > 0.');

        // Contract must still be active and amount_paid must not be falsely inflated
        $this->assertEquals('active', $contract->fresh()->status);
        $this->assertEquals(0, LeaseInstallment::sum('amount_paid'));

        // 2. Real settlement of 12,000,000 must succeed, create transactions, and allocate
        $settleResult = $this->leaseService->settleContract(
            $contract->id,
            [
                'settlement_amount' => 12000000,
                'payment_method' => 'TM',
                'cash_id' => $this->store->id,
                'notes' => 'Tất toán hợp đồng thực tế',
            ],
            $this->adminUser
        );

        $this->assertSame('completed', $settleResult->status);
        $this->assertEquals(12000000, LeaseInstallment::sum('amount_paid'));
        $this->assertGreaterThan(0, Transaction::count());
        $this->assertEquals(12000000, DB::table('lease_payment_allocations')->sum('amount'));
    }

    /**
     * R05: Payment reversal must NOT hard-delete allocations;
     * must retain status='reversed', link reversal transaction, and preserve cash_id/bank_id.
     */
    public function testR05ReversalRetainsAllocationAndPreservesCashLink()
    {
        $this->testLeasePaymentReversal();

        // 1. Counter transaction (CHI) must have cash_id preserved
        $reversalTx = Transaction::where('type', Transaction::CHI)->firstOrFail();
        $this->assertEquals($this->store->id, $reversalTx->cash_id);

        // 2. Allocation must NOT be deleted, but marked reversed
        $allocations = LeasePaymentAllocation::all();
        $this->assertGreaterThan(0, $allocations->count(), 'Allocations must not be hard deleted on reversal.');
        $reversedAlloc = $allocations->first();
        $this->assertEquals('reversed', $reversedAlloc->status);
        $this->assertEquals($reversalTx->id, $reversedAlloc->reversal_transaction_id);
    }

    /**
     * R06: Cash summary must include operating bank expenses in deductions.
     */
    public function testR06CashSummaryIncludesBankExpense()
    {
        // Add expense transaction for company bank
        Transaction::create([
            'store_id' => $this->store->id,
            'type' => Transaction::CHI,
            'value' => 250000,
            'payment_method' => 2, // Bank transfer
            'bank_owner_type' => 'company',
            'name' => 'Chi phí vận hành',
            'created_at' => '2026-09-16 12:00:00',
        ]);

        $summary = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        // Bank expense of 250,000 must result in -250,000 net bank company balance
        $this->assertEquals(-250000, $summary['total_bank_company']);
    }

    /**
     * R07: Closed cash register cannot be overwritten.
     */
    public function testR07ClosedCashRegisterCannotBeOverwritten()
    {
        $first = $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 0, null, $this->adminUser->id);
        $this->assertEquals('closed', $first->status);

        $exceptionThrown = false;
        try {
            $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 100, 'Review attempt overwrite', $this->adminUser->id);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('đã được chốt', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Re-closing an already closed register must throw Exception.');
        $this->assertEquals(0, $first->fresh()->actual_cash_counted);
    }

    /**
     * R03: Staff cannot read other store register history using store_id filter.
     */
    public function testR03StaffCannotReadOtherStoreHistoryWithFilter()
    {
        $otherStore = Store::create(['store_name' => 'Store Chi Nhánh Khác', 'kind' => 'physical']);
        $this->cashRegisterService->closeDailyRegister($otherStore->id, '2026-09-16', 0, null, $this->adminUser->id);

        Auth::setUser($this->staffUser);
        $response = app(DailyCashRegisterController::class)->history(Request::create('/review', 'GET', ['store_id' => $otherStore->id]));

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * N02: Contract settlement with discount sets outstanding_balance to 0 upon completion;
     * rejects overpayment that exceeds remaining debt.
     */
    public function testN02DiscountedSettlementZeroDebtAndOverpaymentRejection()
    {
        $this->testLeaseContractEarlySettlement();
        $contract = LeaseContract::first();
        DB::table('lease_payment_allocations')->delete();
        DB::table('transactions')->delete();
        DB::table('lease_installments')->update(['amount_paid' => 0, 'status' => 'unpaid']);
        $contract->update(['status' => 'active', 'discount_amount' => 0, 'settled_at' => null]);

        // 1. Overpayment must be rejected (13,000,000 > 12,000,000)
        $overpayCaught = false;
        try {
            $this->leaseService->settleContract($contract->id, [
                'settlement_amount' => 13000000,
                'payment_method' => 'TM',
            ], $this->adminUser);
        } catch (ValidationException $e) {
            $overpayCaught = true;
            $this->assertArrayHasKey('settlement_amount', $e->errors());
        }
        $this->assertTrue($overpayCaught, 'Overpayment beyond remaining debt must throw ValidationException.');

        // 2. Settlement with discount: Pay 10,000,000 + Discount 2,000,000
        $settleResult = $this->leaseService->settleContract($contract->id, [
            'settlement_amount' => 10000000,
            'discount_amount' => 2000000,
            'payment_method' => 'TM',
        ], $this->adminUser);

        $this->assertSame('completed', $settleResult->status);
        $this->assertEquals(2000000, $settleResult->fresh()->discount_amount);
        $this->assertNotNull($settleResult->fresh()->settled_at);

        // Dynamic debt metric must be 0, NOT 2,000,000
        $metrics = $this->leaseService->index(['id' => $contract->id], $this->adminUser)->first();
        $this->assertEquals(0, $metrics->outstanding_balance);
        $this->assertEquals(10000000, $metrics->total_paid);

        // A discount is an adjustment, not collected cash and must remain
        // visible in the ledger without inflating amount_paid.
        $this->assertEquals(10000000, LeaseInstallment::where('lease_contract_id', $contract->id)->sum('amount_paid'));
        $this->assertEquals(2000000, LeaseInstallment::where('lease_contract_id', $contract->id)->get()->sum('adjustment_amount'));
        $stats = $this->leaseService->getStats([], $this->adminUser);
        $this->assertEquals(10000000, $stats['total_collected']);
        $this->assertEquals(2000000, $stats['total_discounts']);
        $this->assertEquals(0, $stats['total_outstanding']);

        // Reversing a real receipt re-opens exactly the remaining debt while
        // preserving the approved discount; collecting it again closes the
        // contract without creating a second discount.
        $actualAllocation = LeasePaymentAllocation::where('lease_contract_id', $contract->id)
            ->where('status', LeasePaymentAllocation::STATUS_ACTIVE)
            ->whereNotNull('transaction_id')
            ->orderBy('id')
            ->firstOrFail();
        $reversedAmount = (float) $actualAllocation->amount;
        $this->leaseService->reverseAllocation($actualAllocation->id, 'Kiểm tra đảo thu và thu lại', $this->adminUser);
        $afterReverse = $this->leaseService->index(['id' => $contract->id], $this->adminUser)->first();
        $this->assertEquals($reversedAmount, (float) $afterReverse->outstanding_balance);
        $this->assertSame(LeaseContract::STATUS_ACTIVE, $contract->fresh()->status);

        $this->leaseService->allocatePayment($contract->id, [
            'amount' => $reversedAmount,
            'payment_method' => 'TM',
            'notes' => 'Thu lại sau đảo thu',
        ], $this->adminUser);
        $afterRecollect = $this->leaseService->index(['id' => $contract->id], $this->adminUser)->first();
        $this->assertEquals(0, $afterRecollect->outstanding_balance);
        $this->assertEquals(10000000, $this->leaseService->getStats([], $this->adminUser)['total_collected']);
        $this->assertEquals(2000000, $this->leaseService->getStats([], $this->adminUser)['total_discounts']);
    }

    /**
     * N03: Cash register preserves bank expenses before and after closing.
     */
    public function testN03ClosedRegisterRetainsBankExpenses()
    {
        Transaction::create([
            'store_id' => $this->store->id,
            'type' => Transaction::CHI,
            'value' => 250000,
            'payment_method' => 2,
            'bank_owner_type' => 'company',
            'name' => 'Chi phí vận hành công ty',
            'created_at' => '2026-09-16 12:00:00',
        ]);

        $before = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        $this->assertEquals(-250000, $before['total_bank_company']);

        $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 0, null, $this->adminUser->id);

        $after = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        // Bank expenses must not become 0 after closing
        $this->assertEquals(-250000, $after['total_bank_company']);
        $this->assertEquals(250000, $after['other_expense_bank_company']);
    }

    /**
     * N04: Duty schedule endpoint does not expose plaintext staff id_card.
     */
    public function testN04DutySchedulesDoesNotExposeUnmaskedIdCard()
    {
        $staff = StaffProfile::create([
            'staff_code' => 'NV-REVIEW',
            'full_name' => 'Nhân viên bảo mật',
            'phone' => '0901234567',
            'id_card' => '000123456789',
            'store_id' => $this->store->id,
        ]);

        $this->hrService->saveStoreDutySchedule([
            'store_id' => $this->store->id,
            'duty_date' => '2026-09-16',
            'staff_id' => $staff->id,
            'staff_name' => 'Nhân viên bảo mật',
        ], $this->adminUser->id);

        Auth::setUser($this->staffUser);
        $response = app(HrController::class)->dutySchedules(Request::create('/review-duty', 'GET', ['date' => '2026-09-16']));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('000123456789', $response->getContent(), 'Plaintext id_card must not be exposed in duty roster endpoint.');
    }

    /**
     * N05: Vehicle exchange retains true origin store (A -> B, not B -> B) and queries real cash_id.
     */
    public function testN05ExchangeVehiclePreservesOriginStoreAndUsesValidCashId()
    {
        $storeA = $this->store;
        $storeB = Store::create(['store_name' => 'Cơ sở B', 'kind' => 'physical']);
        // Keep the fixture aligned with the minimal cash table used by this
        // test base; the transfer service only needs the real cash account id.
        $cashB = Cash::create(['store_id' => $storeB->id, 'status' => 'Active']);

        $customer = \App\Entities\Customer::create(['name' => 'Khách Đổi Xe', 'phone' => '0988776655']);
        $oldVehicle = Vehicle::create(['license' => '29A-ORIGIN', 'name' => 'Xe Cũ', 'status' => Vehicle::STATUS_USING, 'store_id' => $storeA->id, 'current_store_id' => $storeA->id, 'odometer' => 1000]);
        $newVehicle = Vehicle::create(['license' => '29B-DEST', 'name' => 'Xe Mới', 'status' => Vehicle::STATUS_READY, 'store_id' => $storeB->id, 'current_store_id' => $storeB->id, 'odometer' => 500]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'store_id' => $storeA->id,
            'order_status' => 'renting',
            'contract_number' => 'HD-EXCHANGE-01',
        ]);
        OrderVehicleDetail::create([
            'order_id' => $order->id,
            'vehicle_id' => $oldVehicle->id,
            'vehicle_name' => $oldVehicle->name,
            'rent_at' => '2026-09-16 08:00:00',
            'price' => 200000,
        ]);

        $service = app(VehicleTransferService::class);
        $result = $service->processVehicleExchange(
            $order->id,
            $oldVehicle->id,
            $newVehicle->id,
            [
                'exchange_store_id' => $storeB->id,
                'price_difference' => 100000,
                'payment_method' => 'TM',
                'reason' => 'Đổi xe tại cơ sở B',
            ],
            $this->adminUser
        );

        $this->assertNotEmpty($result['amendment_code']);

        // 1. Transaction cash_id must be the Cash account ID, NOT the store ID
        $tx = Transaction::where('order_id', $order->id)->where('type', Transaction::THU)->firstOrFail();
        $this->assertEquals($cashB->id, $tx->cash_id, 'cash_id must point to the actual Cash account, not store ID.');

        // 2. Old vehicle movement must record from storeA to storeB
        $event = VehicleLocationEvent::where('vehicle_id', $oldVehicle->id)->firstOrFail();
        $this->assertEquals($storeA->id, $event->from_store_id, 'from_store_id must be the vehicle origin store A.');
        $this->assertEquals($storeB->id, $event->to_store_id, 'to_store_id must be exchange store B.');
    }

    /**
     * N01: Dedicated migrations successfully upgrade existing tables.
     */
    public function testN01UpgradeMigrationAddsColumnsToExistingTable()
    {
        $this->assertTrue(Schema::hasColumn('lease_payment_allocations', 'status'));
        $this->assertTrue(Schema::hasColumn('lease_payment_allocations', 'reversal_transaction_id'));
        $this->assertTrue(Schema::hasColumn('lease_contracts', 'discount_amount'));
        $this->assertTrue(Schema::hasColumn('lease_contracts', 'settled_at'));
        $this->assertTrue(Schema::hasColumn('daily_cash_registers', 'other_expense_bank_company'));
        $this->assertTrue(Schema::hasColumn('daily_cash_registers', 'other_expense_bank_personal'));
    }
}
