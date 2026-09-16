<?php

namespace Tests\Unit;

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadControllerSafetyTest extends TestCase
{
    private $store;
    private $otherStore;
    private $staff;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['lead_logs', 'leads', 'users', 'roles', 'stores'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('stores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('store_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('entry_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone');
            $table->string('vehicle_name')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('pickup_location')->nullable();
            $table->dateTime('rent_at')->nullable();
            $table->dateTime('return_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('lead_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('content')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'slug' => 'quan-tri-vien'],
            ['id' => 2, 'name' => 'Vận hành', 'slug' => 'van-hanh'],
        ]);
        $this->store = Store::create(['store_name' => 'Cơ sở được phép']);
        $this->otherStore = Store::create(['store_name' => 'Cơ sở khác']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'role_id' => 1,
        ]);
        $this->staff = User::create([
            'name' => 'Nhân viên', 'email' => 'staff@example.test', 'role_id' => 2,
            'store_id' => $this->store->id,
        ]);
    }

    public function testCreateUsesAllowlistAndKeepsCoreLeadWorkingBeforeKpiMigration()
    {
        Auth::setUser($this->staff);
        $response = app(LeadController::class)->create(Request::create('/lead', 'POST', [
            'customer_name' => 'Khách kiểm thử',
            'customer_phone' => '0901000000',
            'store_id' => $this->otherStore->id,
            'user_id' => $this->admin->id,
            'order_id' => 999,
            'source_channel' => 'Facebook',
            'campaign_name' => 'Không được ghi khi schema chưa sẵn sàng',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $lead = Lead::firstOrFail();
        $this->assertSame($this->store->id, (int) $lead->store_id);
        $this->assertSame($this->staff->id, (int) $lead->user_id);
        $this->assertNull($lead->order_id);
        $this->assertStringNotContainsString('source_channel', $response->getContent());
    }

    public function testUpdateCannotMoveLeadOrOverwriteSystemOwnershipFields()
    {
        $lead = Lead::create([
            'customer_name' => 'Khách cũ',
            'customer_phone' => '0902000000',
            'store_id' => $this->store->id,
            'user_id' => $this->staff->id,
            'status' => 'pending',
        ]);
        Auth::setUser($this->staff);

        $response = app(LeadController::class)->update(Request::create('/lead/' . $lead->id, 'PUT', [
            'customer_name' => 'Khách đã sửa',
            'customer_phone' => '0902000001',
            'store_id' => $this->otherStore->id,
            'user_id' => $this->admin->id,
            'order_id' => 123,
            'utm_campaign' => 'schema-chua-co',
        ]), $lead);

        $this->assertSame(200, $response->getStatusCode());
        $lead->refresh();
        $this->assertSame('Khách đã sửa', $lead->customer_name);
        $this->assertSame($this->store->id, (int) $lead->store_id);
        $this->assertSame($this->staff->id, (int) $lead->user_id);
        $this->assertNull($lead->order_id);
    }

    public function testUserJsonNeverExposesWalletSecrets()
    {
        $user = new User();
        $user->forceFill([
            'name' => 'Kiểm thử',
            'wallet_private_key' => 'private-key-must-stay-server-side',
            'mnemonic' => 'seed words must stay server side',
        ]);

        $serialized = $user->toArray();
        $this->assertArrayNotHasKey('wallet_private_key', $serialized);
        $this->assertArrayNotHasKey('mnemonic', $serialized);
    }
}
