<?php

namespace Tests\Unit;

use App\Support\OperationalSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalSchemaMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('lease_payment_allocations');
        Schema::dropIfExists('lease_contracts');
        Schema::dropIfExists('daily_cash_registers');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('business_assets');
        Schema::dropIfExists('accounting_vat_documents');

        Schema::create('lease_payment_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('amount', 15, 2)->default(0);
        });
        Schema::create('lease_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('contract_code');
        });
        Schema::create('daily_cash_registers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('penalty_bank_personal', 15, 2)->default(0);
            $table->decimal('penalty_bank_company', 15, 2)->default(0);
        });

        DB::table('lease_payment_allocations')->insert(['amount' => 125000]);
        DB::table('lease_contracts')->insert(['contract_code' => 'LEGACY-001']);
        DB::table('daily_cash_registers')->insert([
            'penalty_bank_personal' => 10000,
            'penalty_bank_company' => 20000,
        ]);
    }

    public function testUpgradeMigrationsAddRequiredColumnsAndPreserveLegacyRows()
    {
        require_once database_path('migrations/2026_09_16_000006_add_reversal_fields_to_lease_payment_allocations_table.php');
        require_once database_path('migrations/2026_09_16_000007_add_bank_expenses_to_daily_cash_registers_table.php');

        (new \AddReversalFieldsToLeasePaymentAllocationsTable())->up();
        (new \AddBankExpensesToDailyCashRegistersTable())->up();

        $report = app(OperationalSchema::class)->report();
        $this->assertTrue($report['profiles']['lease']['ready']);
        $this->assertTrue($report['profiles']['cash_register']['ready']);
        $this->assertEquals(125000, DB::table('lease_payment_allocations')->value('amount'));
        $this->assertEquals('active', DB::table('lease_payment_allocations')->value('status'));
        $this->assertEquals('LEGACY-001', DB::table('lease_contracts')->value('contract_code'));
        $this->assertEquals(0, DB::table('lease_contracts')->value('discount_amount'));
        $this->assertEquals(10000, DB::table('daily_cash_registers')->value('penalty_bank_personal'));
        $this->assertEquals(0, DB::table('daily_cash_registers')->value('other_income_bank_company'));

        // Re-running up() must remain safe for a retry after an interrupted deploy.
        (new \AddReversalFieldsToLeasePaymentAllocationsTable())->up();
        (new \AddBankExpensesToDailyCashRegistersTable())->up();
        $retryReport = app(OperationalSchema::class)->report();
        $this->assertTrue($retryReport['profiles']['lease']['ready']);
        $this->assertTrue($retryReport['profiles']['cash_register']['ready']);
    }

    public function testUpgradeMigrationsCanRollbackWithoutDeletingLegacyRows()
    {
        require_once database_path('migrations/2026_09_16_000006_add_reversal_fields_to_lease_payment_allocations_table.php');
        require_once database_path('migrations/2026_09_16_000007_add_bank_expenses_to_daily_cash_registers_table.php');

        $leaseMigration = new \AddReversalFieldsToLeasePaymentAllocationsTable();
        $cashMigration = new \AddBankExpensesToDailyCashRegistersTable();
        $leaseMigration->up();
        $cashMigration->up();
        $cashMigration->down();
        $leaseMigration->down();

        $this->assertFalse(Schema::hasColumn('lease_payment_allocations', 'reversal_reason'));
        $this->assertFalse(Schema::hasColumn('daily_cash_registers', 'other_expense_bank_company'));
        $this->assertEquals(125000, DB::table('lease_payment_allocations')->value('amount'));
        $this->assertEquals('LEGACY-001', DB::table('lease_contracts')->value('contract_code'));
        $this->assertEquals(20000, DB::table('daily_cash_registers')->value('penalty_bank_company'));
    }

    public function testAttendanceMigrationIsRetrySafeAndReversible()
    {
        require_once database_path('migrations/2026_09_16_000008_create_staff_attendances_table.php');
        $migration = new \CreateStaffAttendancesTable();

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('staff_attendances'));
        $this->assertTrue(app(OperationalSchema::class)->isReady('attendance'));

        $migration->down();
        $this->assertFalse(Schema::hasTable('staff_attendances'));
    }

    public function testLeadAttributionMigrationPreservesLegacyRowsAndRollsBack()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('customer_phone')->nullable();
        });
        DB::table('leads')->insert(['customer_phone' => '0901000000']);

        require_once database_path('migrations/2026_09_16_000009_add_attribution_to_leads_table.php');
        $migration = new \AddAttributionToLeadsTable();
        $migration->up();
        $migration->up();

        $this->assertTrue(app(OperationalSchema::class)->isReady('kpi'));
        $this->assertEquals('0901000000', DB::table('leads')->value('customer_phone'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('leads', 'campaign_name'));
        $this->assertEquals('0901000000', DB::table('leads')->value('customer_phone'));
    }

    public function testAccountingMigrationIsRetrySafeAndReversible()
    {
        require_once database_path('migrations/2026_09_17_000010_create_accounting_vat_and_assets_tables.php');
        $migration = new \CreateAccountingVatAndAssetsTables();
        $migration->up();
        $migration->up();

        $this->assertTrue(app(OperationalSchema::class)->isReady('accounting'));
        DB::table('business_assets')->insert([
            'asset_code' => 'TS-LEGACY',
            'name' => 'Tài sản kiểm thử',
            'purchase_cost' => 1000000,
            'residual_value' => 0,
            'depreciation_months' => 12,
            'status' => 'active',
        ]);
        $this->assertEquals(1, DB::table('business_assets')->count());

        $migration->down();
        $this->assertFalse(Schema::hasTable('business_assets'));
        $this->assertFalse(Schema::hasTable('accounting_vat_documents'));
    }

    public function testOperationalMigrationChecksumsMatchApprovedManifest()
    {
        $manifestPath = database_path('migrations/operational-checksums.json');
        $manifest = json_decode(file_get_contents($manifestPath), true);

        $this->assertNotEmpty($manifest);
        foreach ($manifest as $migration => $approvedChecksum) {
            $path = database_path('migrations/' . $migration . '.php');
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $canonicalContents = str_replace(["\r\n", "\r"], "\n", $contents);
            $this->assertSame($approvedChecksum, hash('sha256', $canonicalContents), $migration);
        }
    }
}
