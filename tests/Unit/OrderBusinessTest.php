<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vehicle;
use App\Entities\Customer;
use App\Entities\AddOnOrder;
use App\Models\OrderVehicleDetail;
use App\Models\Transaction;
use App\Models\ActivityLog;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderBusinessTest extends TestCase
{
    /**
     * Test Order relationships to ensure all related business entities link correctly.
     */
    public function testOrderRelationships()
    {
        $order = new Order();

        $this->assertInstanceOf(BelongsTo::class, $order->store(), "Order must belong to a Store");
        $this->assertInstanceOf(BelongsToMany::class, $order->vehicles(), "Order must have many Vehicles through order_vehicle_details");
        $this->assertInstanceOf(BelongsTo::class, $order->customer(), "Order must belong to a Customer");
        $this->assertInstanceOf(HasMany::class, $order->orderItems(), "Order must have many OrderVehicleDetails (orderItems)");
        $this->assertInstanceOf(HasMany::class, $order->transactions(), "Order must have many Transactions (payments/refunds)");
        $this->assertInstanceOf(HasMany::class, $order->activityLogs(), "Order must have many ActivityLogs");
        $this->assertInstanceOf(HasMany::class, $order->addOnOrders(), "Order must have many AddOnOrders");
        $this->assertInstanceOf(HasMany::class, $order->leads(), "Order must have many Leads");
    }

    /**
     * Test Order instantiation and financial fields assignment.
     */
    public function testOrderFinancialInstantiation()
    {
        $order = new Order([
            'customer_id' => 10,
            'store_id' => 1,
            'total' => 1500000,
            'deposit_amount' => 2000000,
            'first_deposit_amount' => 2000000,
            'additional_deposit_amount' => 0,
            'status' => 1, // active / renting
        ]);

        $this->assertEquals(10, $order->customer_id);
        $this->assertEquals(1, $order->store_id);
        $this->assertEquals(1500000, $order->total);
        $this->assertEquals(2000000, $order->deposit_amount);
        $this->assertEquals(2000000, $order->first_deposit_amount);
        $this->assertEquals(0, $order->additional_deposit_amount);
        $this->assertEquals(1, $order->status);
    }
}
