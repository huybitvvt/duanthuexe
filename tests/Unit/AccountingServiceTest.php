<?php

namespace Tests\Unit;

use App\Http\Services\AccountingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('business_assets');
        Schema::dropIfExists('accounting_vat_documents');
        Schema::dropIfExists('stores');
        Schema::create('stores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->timestamps();
        });
        Schema::create('accounting_vat_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type');
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->string('counterparty_name');
            $table->string('tax_code')->nullable();
            $table->decimal('amount_before_tax', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('business_assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->unsignedInteger('depreciation_months')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function testVatAndAssetCalculationsAreServerControlled()
    {
        $service = app(AccountingService::class);
        $vat = $service->saveVatDocument([
            'document_type' => 'output',
            'invoice_number' => 'HD-001',
            'invoice_date' => '2026-09-17',
            'counterparty_name' => 'Khách hàng A',
            'amount_before_tax' => 1000000,
            'vat_rate' => 8,
            'payment_status' => 'paid',
        ], 1);
        $asset = $service->saveAsset([
            'asset_code' => 'TS-001',
            'name' => 'Máy tính quầy',
            'purchase_cost' => 12000000,
            'residual_value' => 0,
            'depreciation_months' => 24,
            'status' => 'active',
        ], 1);

        $this->assertEquals(80000, $vat->vat_amount);
        $this->assertEquals(1080000, $vat->total_amount);
        $this->assertEquals(500000, $asset->monthly_depreciation);

        $dashboard = $service->dashboard([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'store_id' => null,
        ]);
        $this->assertEquals(80000, $dashboard['summary']['output_vat']);
        $this->assertEquals(80000, $dashboard['summary']['vat_payable_estimate']);
        $this->assertEquals(12000000, $dashboard['summary']['asset_purchase_cost']);
        $this->assertEquals(500000, $dashboard['summary']['monthly_depreciation']);
    }
}
