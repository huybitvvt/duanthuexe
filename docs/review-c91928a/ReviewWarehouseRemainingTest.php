<?php
// Evidence of remaining defects, kept outside the acceptance suite.
require_once __DIR__ . '/../../tests/Unit/HimotoWarehouseTransferTest.php';

class ReviewWarehouseRemainingTest extends \Tests\Unit\HimotoWarehouseTransferTest
{
    public function testReviewNowExchangeInventsCashIdAndLosesOrigin()
    {
        $this->test_r12_vehicle_exchange_with_price_difference_and_cross_store();
        $tx = \App\Models\Transaction::firstOrFail();
        $this->assertEquals($this->storeB->id, $tx->cash_id);
        $old = \App\Models\Vehicle::where('license', '29A-OLD')->firstOrFail();
        $event = \App\Models\VehicleLocationEvent::where('vehicle_id', $old->id)->firstOrFail();
        $this->assertEquals($this->storeB->id, $event->from_store_id);
        $this->assertEquals($this->storeB->id, $event->to_store_id);
        $this->assertNotEquals($this->storeA->id, $event->from_store_id);
    }
}
