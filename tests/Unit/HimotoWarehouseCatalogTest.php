<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Support\HimotoStores;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HimotoWarehouseCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stores');
        Schema::create('stores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('store_name');
            $table->string('store_address')->nullable();
            $table->string('store_phone')->nullable();
            $table->string('code')->nullable();
            $table->string('kind')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id');
        });
    }

    public function testCatalogMigrationReusesExistingIdsAndOnlyDeletesUnreferencedExtras(): void
    {
        $langId = DB::table('stores')->insertGetId(['code' => 'LANG', 'store_name' => 'Láng']);
        $legacyId = DB::table('stores')->insertGetId(['code' => 'OLD', 'store_name' => 'Cơ sở có đơn cũ']);
        $unusedId = DB::table('stores')->insertGetId(['code' => 'UNUSED', 'store_name' => 'Kho thử nghiệm']);
        DB::table('orders')->insert(['store_id' => $legacyId]);

        require_once __DIR__ . '/../../database/migrations/2026_09_22_000001_lock_himoto_warehouse_catalog.php';
        $migration = new \LockHimotoWarehouseCatalog();
        $migration->up();
        $migration->up();

        $this->assertSame(6, HimotoStores::query()->count());
        $this->assertSame('CS1', Store::findOrFail($langId)->code);
        $this->assertSame('264 đường Láng, Đống Đa', Store::findOrFail($langId)->store_address);
        $this->assertSame('inactive', Store::findOrFail($legacyId)->status);
        $this->assertSame($legacyId, (int) DB::table('orders')->value('store_id'));
        $this->assertNull(Store::find($unusedId));
        $this->assertSame(7, Store::count());
    }

    public function testCatalogIncludesExactlyTheSixRequestedLocations(): void
    {
        $this->assertSame(['CS1', 'CS2', 'CS3', 'CS4', 'CS5', 'CS6'], HimotoStores::codes());
        $this->assertSame('Kho sở hữu', HimotoStores::definition('CS6')['store_name']);
        $this->assertSame('lease_to_own', HimotoStores::definition('CS6')['kind']);
        $this->assertSame('476 Quang Trung, Hà Đông', HimotoStores::definition('CS5')['store_address']);
    }

    public function testInactiveDuplicateCodeNeverAppearsInContractStoreOptions(): void
    {
        require_once __DIR__ . '/../../database/migrations/2026_09_22_000001_lock_himoto_warehouse_catalog.php';
        $firstId = DB::table('stores')->insertGetId(['code' => 'CS1', 'store_name' => 'CS 1']);
        $duplicateId = DB::table('stores')->insertGetId(['code' => 'CS1', 'store_name' => 'Kho nhân đôi']);
        DB::table('orders')->insert(['store_id' => $duplicateId]);
        (new \LockHimotoWarehouseCatalog())->up();

        $this->assertSame(6, HimotoStores::query()->count());
        $this->assertSame('inactive', Store::findOrFail($duplicateId)->status);
        $this->assertSame('opening', Store::findOrFail($firstId)->status);
        $this->assertFalse(HimotoStores::isCanonical(Store::findOrFail($duplicateId)));
    }
}
