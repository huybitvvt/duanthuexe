<?php

namespace Tests\Unit;

use App\Entities\Customer;
use App\Http\Services\HimotoLegalDocumentService;
use App\Models\LeaseContract;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\Vehicle;
use Tests\TestCase;

class HimotoLegalDocumentsTest extends TestCase
{
    public function testBlankDraftHandoverCanBePrintedBeforeCustomerAndVehicleAreKnown(): void
    {
        $order = new Order(['order_status' => 'draft']);
        $order->id = 21;
        $order->setRelation('customer', null);
        $order->setRelation('orderItems', collect());

        $html = app(HimotoLegalDocumentService::class)->rentalHandover($order);
        $this->assertContains('NHÁP-21/BBGX', $html);
        $this->assertContains('Chưa có thông tin xe giao nhận', $html);
        $this->assertContains('NGƯỜI GIÁM HỘ', $html);
    }

    public function testRentalHandoverUsesSavedCustomerAndVehicle(): void
    {
        $customer = new Customer(['name' => 'Nguyễn Văn A', 'id_card' => '001234567890', 'address' => 'Hà Nội']);
        $vehicle = new Vehicle(['brand' => 'Vinfast', 'name' => 'Xe 50cc', 'license' => '29A-12345', 'engine' => 'MAY-01', 'chassis' => 'KHUNG-01']);
        $item = new OrderVehicleDetail(['rent_at' => '2026-09-22 08:00:00']);
        $item->setRelation('vehicle', $vehicle);
        $order = new Order(['order_status' => 'draft', 'draft_reference' => 'GIAY-01', 'contract_signed_on' => '2026-09-22', 'guardian_name' => 'Bà Trần Thị B']);
        $order->id = 7;
        $order->setRelation('customer', $customer);
        $order->setRelation('orderItems', collect([$item]));

        $html = app(HimotoLegalDocumentService::class)->rentalHandover($order);
        $this->assertContains('BIÊN BẢN BÀN GIAO XE', $html);
        $this->assertContains('GIAY-01/BBGX', $html);
        $this->assertContains('MAY-01', $html);
        $this->assertContains('BẢN NHÁP', $html);
        $this->assertContains('Bà Trần Thị B', $html);
    }

    public function testEachLeaseTermRendersItsOwnAnnexAndHandover(): void
    {
        foreach ([6 => ['SH06', '60 ngày'], 12 => ['SH12', '270 ngày'], 24 => ['SH24', '18 tháng']] as $term => [$variant, $milestone]) {
            $customer = new Customer(['name' => 'Khách thử', 'phone' => '0912345678', 'id_card' => '001234567890']);
            $vehicle = new Vehicle(['brand' => 'Vinfast', 'name' => 'Feliz', 'license' => '29B-56789', 'sale_price' => 24000000]);
            $contract = new LeaseContract([
                'contract_code' => 'SH-' . $term,
                'installment_count' => $term,
                'start_date' => '2026-09-22',
            ]);
            $contract->setRelation('customer', $customer);
            $contract->setRelation('vehicle', $vehicle);
            $contract->setRelation('originStore', new Store(['store_address' => '264 đường Láng', 'store_phone' => '0886184116']));
            $html = app(HimotoLegalDocumentService::class)->leaseAnnex($contract);
            $this->assertContains($variant, $html);
            $this->assertContains($milestone, $html);
            $this->assertContains('29B-56789', $html);
            $this->assertContains('24.000.000', $html);
            $this->assertContains('264 đường Láng', $html);
            $handover = app(HimotoLegalDocumentService::class)->leaseHandover($contract);
            $this->assertContains('BIÊN BẢN BÀN GIAO XE', $handover);
            $this->assertContains('SH-' . $term, $handover);
        }
    }

    public function testAnnexCannotUseDifferentInstallmentTerm(): void
    {
        $contract = new LeaseContract(['contract_code' => 'SH-12', 'installment_count' => 12, 'start_date' => '2026-09-22']);
        $contract->setRelation('customer', new Customer(['name' => 'Khách thử']));
        $contract->setRelation('vehicle', new Vehicle(['name' => 'Xe thử']));
        $contract->setRelation('originStore', new Store(['store_address' => 'Hà Nội']));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(HimotoLegalDocumentService::class)->leaseAnnex($contract, 6);
    }
}
