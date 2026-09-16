<?php
// Evidence of remaining defects at c91928a, NOT acceptance expectations.
require_once __DIR__ . '/../../tests/Unit/HimotoCashRegisterAndHrTest.php';

use App\Models\LeaseContract;
use App\Models\LeaseInstallment;
use App\Models\Transaction;
use App\Models\StaffProfile;
use App\Http\Controllers\HrController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class ReviewRemainingTest extends \Tests\Unit\HimotoCashRegisterAndHrTest
{
    public function testReviewNowDiscountStillLeavesDebtAfterCompletion()
    {
        $this->testLeaseContractEarlySettlement();
        $contract = LeaseContract::first();
        $metrics = $this->leaseService->index(['id' => $contract->id], $this->adminUser)->first();
        $this->assertSame('completed', $contract->status);
        $this->assertEquals(12000000, LeaseInstallment::sum('amount_paid'));
        $this->assertEquals(10000000, $contract->activeAllocations()->sum('amount'));
        $this->assertEquals(2000000, $metrics->outstanding_balance);
    }

    public function testReviewNowClosingLosesBankExpense()
    {
        Transaction::create([
            'store_id' => $this->store->id, 'type' => Transaction::CHI,
            'value' => 250000, 'payment_method' => 2, 'bank_owner_type' => 'company',
            'name' => 'Review operating cost', 'created_at' => '2026-09-16 12:00:00',
        ]);
        $before = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        $this->cashRegisterService->closeDailyRegister($this->store->id, '2026-09-16', 0, null, $this->adminUser->id);
        $after = $this->cashRegisterService->getDailySummary($this->store->id, '2026-09-16');
        $this->assertEquals(-250000, $before['total_bank_company']);
        $this->assertEquals(0, $after['total_bank_company']);
    }

    public function testReviewNowDutyEndpointExposesUnmaskedIdCard()
    {
        $staff = StaffProfile::create([
            'staff_code' => 'REVIEW-ONLY', 'full_name' => 'Review person',
            'phone' => '0000000000', 'id_card' => '000123456789', 'store_id' => $this->store->id,
        ]);
        $this->hrService->saveStoreDutySchedule([
            'store_id' => $this->store->id, 'duty_date' => '2026-09-16',
            'staff_id' => $staff->id, 'staff_name' => 'Review person',
        ], $this->adminUser->id);
        Auth::setUser($this->staffUser);
        $response = app(HrController::class)->dutySchedules(Request::create('/review', 'GET', ['date' => '2026-09-16']));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('000123456789', $response->getContent());
    }

    public function testReviewNowExistingAllocationTableIsNotUpgraded()
    {
        // SQLite memory only; mimic an existing table from the previous schema.
        Schema::drop('lease_payment_allocations');
        Schema::create('lease_payment_allocations', function ($table) {
            $table->increments('id');
            $table->decimal('amount', 15, 2);
        });
        require_once __DIR__ . '/../../database/migrations/2026_09_16_000001_create_lease_contracts_and_debt_tables.php';
        (new \CreateLeaseContractsAndDebtTables())->up();
        $this->assertFalse(Schema::hasColumn('lease_payment_allocations', 'status'));
        $this->assertFalse(Schema::hasColumn('lease_payment_allocations', 'reversal_transaction_id'));
    }
}
