<?php
// Review evidence only: these assertions describe defects at 38b330a, not desired behavior.
// Run separately with --filter testReview; never add this class to the acceptance suite.
require_once __DIR__ . '/../../tests/Unit/HimotoCashRegisterAndHrTest.php';

use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Transaction;
use App\Models\DailyCashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\DailyCashRegisterController;

class ReproduceReviewTest extends \Tests\Unit\HimotoCashRegisterAndHrTest
{
    public function testReviewZeroSettlementClosesUnpaidContract()
    {
        $this->testLeaseContractEarlySettlement();
        $contract = LeaseContract::first();
        DB::table('lease_payment_allocations')->delete();
        DB::table('transactions')->delete();
        DB::table('lease_installments')->update(['amount_paid' => 0, 'status' => 'unpaid']);
        $contract->update(['status' => 'active']);
        $result = $this->leaseService->settleContract($contract->id, ['settlement_amount' => 0], $this->adminUser);
        $this->assertSame('completed', $result->status);
        $this->assertEquals(12000000, LeaseInstallment::sum('amount_paid'));
        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(0, DB::table('lease_payment_allocations')->sum('amount'));
    }

    public function testReviewReversalLosesAllocationAndCashLink()
    {
        $this->testLeasePaymentReversal();
        $reversal = Transaction::where('type', Transaction::CHI)->firstOrFail();
        $this->assertNull($reversal->cash_id);
        $this->assertNull($reversal->bank_id);
        $this->assertEquals(0, DB::table('lease_payment_allocations')->count());
    }

    public function testReviewCashSummaryOmitsBankExpense()
    {
        Transaction::create([
            'store_id' => $this->store->id, 'type' => Transaction::CHI,
            'value' => 250000, 'payment_method' => 2, 'bank_owner_type' => 'company',
            'name' => 'Chi phí vận hành', 'created_at' => '2026-09-16 12:00:00',
        ]);
        $summary = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        $this->assertEquals(0, $summary['total_bank_company']);
    }

    public function testReviewClosedCashRegisterCanBeOverwritten()
    {
        $first = $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 0, null, $this->adminUser->id);
        $second = $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 100, 'Review only', $this->adminUser->id);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(100, $first->fresh()->actual_cash_counted);
        $this->assertEquals(1, DailyCashRegister::count());
    }

    public function testReviewStaffCanReadOtherStoreHistoryWithFilter()
    {
        $other = \App\Models\Store::create(['store_name' => 'Review other store', 'kind' => 'physical']);
        $this->cashRegisterService->closeDailyRegister($other->id, '2026-09-16', 0, null, $this->adminUser->id);
        Auth::setUser($this->staffUser);
        $response = app(DailyCashRegisterController::class)->history(Request::create('/review', 'GET', ['store_id' => $other->id]));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Review other store', $response->getContent());
    }
}
