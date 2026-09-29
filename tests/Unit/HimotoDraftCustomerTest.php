<?php

namespace Tests\Unit;

use App\Validators\OrderValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HimotoDraftCustomerTest extends TestCase
{
    public function testDraftAllowsMissingPhoneAndCardButIssuedContractRequiresBoth(): void
    {
        $form = ['save_as_draft' => true, 'store_id' => 1, 'total' => 0, 'customer_name' => 'Khách nháp'];
        $this->assertFalse(Validator::make($form, OrderValidator::store(new Request($form)))->fails());

        $issued = array_merge($form, ['save_as_draft' => false]);
        $this->assertTrue(Validator::make($issued, OrderValidator::store(new Request($issued)))->fails());

        $issued['customer_phone'] = '+84912345678';
        $issued['customer_id_card'] = '001234567890';
        $this->assertFalse(Validator::make($issued, OrderValidator::store(new Request($issued)))->fails());

        $issued['customer_phone'] = '1234';
        $this->assertTrue(Validator::make($issued, OrderValidator::store(new Request($issued)))->fails());
    }

    public function testHandoverDraftCanStartWithoutCustomerOrBranchAndNeedsGuardianWhenIssued(): void
    {
        $draft = ['save_as_draft' => true, 'order_mode' => 'handover', 'total' => 0];
        $this->assertFalse(Validator::make($draft, OrderValidator::store(new Request($draft)))->fails());

        $official = $draft + [
            'store_id' => 1,
            'customer_name' => 'Khách nhận xe',
            'customer_phone' => '0912345678',
            'customer_id_card' => '001234567890',
        ];
        $official['save_as_draft'] = false;
        $this->assertTrue(Validator::make($official, OrderValidator::store(new Request($official)))->fails());
        $official['guardian_name'] = 'Người giám hộ';
        $this->assertFalse(Validator::make($official, OrderValidator::store(new Request($official)))->fails());
    }

    public function testDraftCustomerCardMigrationRetainsUniqueRealCard(): void
    {
        Schema::dropIfExists('customers');
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('id_card')->unique();
        });

        require_once __DIR__ . '/../../database/migrations/2026_09_22_000002_allow_draft_customers_without_id_card.php';
        (new \AllowDraftCustomersWithoutIdCard())->up();
        DB::table('customers')->insert([['id_card' => null], ['id_card' => null], ['id_card' => '001234567890']]);
        $this->assertSame(3, DB::table('customers')->count());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('customers')->insert(['id_card' => '001234567890']);
    }

    public function testPaperReferenceCannotReuseAnotherDraftOrOfficialNumber(): void
    {
        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('contract_number')->nullable();
        });
        require_once __DIR__ . '/../../database/migrations/2026_09_22_000005_add_draft_reference_to_orders.php';
        (new \AddDraftReferenceToOrders())->up();
        DB::table('orders')->insert([
            ['contract_number' => 'OFFICIAL-01', 'draft_reference' => null],
            ['contract_number' => null, 'draft_reference' => 'PAPER-01'],
        ]);

        $form = [
            'save_as_draft' => true,
            'store_id' => 1,
            'total' => 0,
            'customer_name' => 'Khách giấy',
            'draft_reference' => 'OFFICIAL-01',
        ];
        $this->assertTrue(Validator::make($form, OrderValidator::store(new Request($form)))->fails());
        $form['draft_reference'] = 'PAPER-01';
        $this->assertTrue(Validator::make($form, OrderValidator::store(new Request($form)))->fails());
        $form['draft_reference'] = 'PAPER-02';
        $this->assertFalse(Validator::make($form, OrderValidator::store(new Request($form)))->fails());
    }

    public function testIntakeMigrationAllowsUnassignedBranchAndStoresGuardian(): void
    {
        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('store_id');
        });
        require_once __DIR__ . '/../../database/migrations/2026_09_24_000001_add_handover_draft_fields_to_orders.php';
        (new \AddHandoverDraftFieldsToOrders())->up();

        DB::table('orders')->insert(['store_id' => null, 'order_mode' => 'handover', 'guardian_name' => 'Bà Lan']);
        $this->assertSame('Bà Lan', DB::table('orders')->value('guardian_name'));
    }
}
