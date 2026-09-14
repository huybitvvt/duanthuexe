<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Vehicle;
use App\Models\Store;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\MaintenanceSchedule;
use App\Models\File;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VehicleBusinessTest extends TestCase
{
    /**
     * Test vehicle business status constants according to backend contract.
     * Must use 'using' instead of arbitrary 'renting'.
     */
    public function testVehicleStatusConstants()
    {
        $this->assertEquals('ready', Vehicle::STATUS_READY);
        $this->assertEquals('using', Vehicle::STATUS_USING);
        $this->assertEquals('pending', Vehicle::STATUS_PENDING);
        $this->assertEquals('repairing', Vehicle::STATUS_REPAIRING);
        $this->assertEquals('broken', Vehicle::STATUS_BROKEN);
        $this->assertEquals('sold', Vehicle::STATUS_SOLD);
        $this->assertEquals('bad_debt', Vehicle::STATUS_BAD_DEBT);
    }

    /**
     * Test vehicle type constants.
     */
    public function testVehicleTypeConstants()
    {
        $this->assertEquals('xeso', Vehicle::TYPE_XESO);
        $this->assertEquals('xega', Vehicle::TYPE_XEGA);
        $this->assertEquals('xecon', Vehicle::TYPE_XECON);
        $this->assertEquals('xesh', Vehicle::TYPE_XE_SH);
    }

    /**
     * Test vehicle fillable fields include essential fleet management columns.
     */
    public function testVehicleFillableAttributes()
    {
        $vehicle = new Vehicle();
        $fillable = $vehicle->getFillable();

        $expectedAttributes = [
            'name',
            'brand',
            'type',
            'store_id',
            'license',
            'status',
            'cost_price',
            'sale_price',
            'odometer'
        ];

        foreach ($expectedAttributes as $attr) {
            $this->assertContains($attr, $fillable, "Attribute {$attr} must be fillable in Vehicle model");
        }
    }

    /**
     * Test vehicle Eloquent relationships structure.
     */
    public function testVehicleEloquentRelationships()
    {
        $vehicle = new Vehicle();

        $this->assertInstanceOf(BelongsTo::class, $vehicle->store());
        $this->assertInstanceOf(BelongsToMany::class, $vehicle->orders());
        $this->assertInstanceOf(HasMany::class, $vehicle->orderVehicleDetails());
        $this->assertInstanceOf(HasMany::class, $vehicle->maintenanceSchedule());
        $this->assertInstanceOf(BelongsToMany::class, $vehicle->images());
    }

    /**
     * Test vehicle instantiation with attributes.
     */
    public function testVehicleInstantiation()
    {
        $vehicle = new Vehicle([
            'name' => 'Honda Vision 2023',
            'license' => '29B1-888.88',
            'status' => Vehicle::STATUS_READY,
            'odometer' => 12500,
            'store_id' => 1,
            'type' => Vehicle::TYPE_XEGA
        ]);

        $this->assertEquals('Honda Vision 2023', $vehicle->name);
        $this->assertEquals('29B1-888.88', $vehicle->license);
        $this->assertEquals(Vehicle::STATUS_READY, $vehicle->status);
        $this->assertEquals(12500, $vehicle->odometer);
        $this->assertEquals(1, $vehicle->store_id);
    }
}
