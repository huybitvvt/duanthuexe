<?php

namespace Tests\Feature\Himoto;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountPricingValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function ($table) {
            $table->increments('id'); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->integer('role_id'); $table->integer('store_id')->nullable();
            $table->string('status')->default('active'); $table->string('phone')->nullable();
            $table->string('address')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('roles', function ($table) { $table->increments('id'); $table->string('slug'); });
        Schema::create('stores', function ($table) { $table->increments('id'); $table->string('store_name'); });
        Schema::create('pricing', function ($table) {
            $table->increments('id'); $table->string('type'); $table->integer('from_year'); $table->integer('to_year');
            $table->integer('from_date'); $table->integer('to_date'); $table->decimal('price', 18, 2);
            $table->string('price_type'); $table->timestamps();
        });
        DB::table('roles')->insert(['id' => 1, 'slug' => 'quan-tri-vien']);
        DB::table('stores')->insert(['id' => 31, 'store_name' => 'Fixture store']);
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs(User::create(['name' => 'Fixture admin', 'email' => 'fixture@example.test',
            'password' => Hash::make('OriginalPass123'), 'role_id' => 1, 'status' => 'active']), 'api');
    }

    public function testPasswordRoutesRejectMissingAndShortPasswordsWithoutChangingHash(): void
    {
        $hash = User::find(1)->password;
        foreach (['change-password', 'change-my-password'] as $route) {
            foreach ([null, '', '123'] as $password) {
                $this->postJson('/api/auth/users/'.$route, ['user_id' => 1, 'password' => $password])->assertStatus(422);
                $this->assertSame($hash, User::find(1)->password);
            }
        }
    }

    public function testSelfPasswordChangeAcceptsCurrentFrontendPayloadAndDeniesOtherUser(): void
    {
        User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => Hash::make('OriginalPass123'), 'role_id' => 1]);
        $this->postJson('/api/auth/users/change-my-password', ['user_id' => 2, 'password' => 'NewPass123'])->assertStatus(400);
        $this->assertTrue(Hash::check('OriginalPass123', User::find(2)->password));
        $this->postJson('/api/auth/users/change-my-password', ['user_id' => 1, 'password' => 'NewPass123'])->assertStatus(200);
        $this->assertTrue(Hash::check('NewPass123', User::find(1)->password));
    }

    public function testOldPasswordMustMatchBeforeLegacyPasswordChange(): void
    {
        $hash = User::find(1)->password;
        $this->postJson('/api/auth/change-pass', ['old_password' => 'WrongPass123', 'new_password' => 'NewPass123',
            'new_password_confirmation' => 'NewPass123'])->assertStatus(422);
        $this->assertSame($hash, User::find(1)->password);
        $this->postJson('/api/auth/change-pass', ['old_password' => 'OriginalPass123', 'new_password' => 'NewPass123',
            'new_password_confirmation' => 'NewPass123'])->assertStatus(201);
        $this->assertTrue(Hash::check('NewPass123', User::find(1)->password));
    }

    public function testAdministratorCannotDeleteOwnSignedInAccount(): void
    {
        $this->deleteJson('/api/auth/users/1')->assertStatus(403);
        $this->assertNull(User::find(1)->deleted_at);
        $this->assertSame('active', User::find(1)->status);
    }

    public function testUserCreationAndUpdateValidateReferencesAndUniqueEmail(): void
    {
        $payload = ['name' => 'New user', 'email' => 'new@example.test', 'password' => 'OriginalPass123', 'role_id' => 1, 'store_id' => 31];
        $this->postJson('/api/auth/users/store', array_merge($payload, ['role_id' => 999]))->assertStatus(422);
        $this->postJson('/api/auth/users/store', array_merge($payload, ['store_id' => 999]))->assertStatus(422);
        $this->assertSame(1, User::count());
        $this->postJson('/api/auth/users/store', $payload)->assertStatus(200);
        $this->postJson('/api/auth/users/store', $payload)->assertStatus(422);
        $this->postJson('/api/auth/users/update', ['id' => 2, 'email' => 'fixture@example.test'])->assertStatus(422);
        $this->postJson('/api/auth/users/update', ['id' => 2, 'name' => 'Updated', 'store_id' => 31])->assertStatus(200);
        $this->assertSame('Updated', User::find(2)->name);
        $this->assertSame(0, DB::transactionLevel());
    }

    private function tariff(): array
    {
        return ['type' => 'xeso', 'from_year' => 2000, 'to_year' => 2026, 'from_date' => 1, 'to_date' => 30, 'price' => 100000, 'price_type' => 'day'];
    }

    public function testInvalidPricingBatchDoesNotPersistEarlierValidRow(): void
    {
        $this->postJson('/api/auth/priceVehicles', ['priceVehicles' => [$this->tariff(), array_merge($this->tariff(), ['id' => 999])]])->assertStatus(422);
        $this->assertSame(0, DB::table('pricing')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function testTariffsRejectNegativePricesAndReversedRanges(): void
    {
        foreach ([['price' => -1], ['from_year' => 2026, 'to_year' => 2000], ['from_date' => 30, 'to_date' => 1], ['from_date' => 'invalid']] as $invalid) {
            $this->postJson('/api/auth/priceVehicles', ['priceVehicles' => [array_merge($this->tariff(), $invalid)]])->assertStatus(422);
        }
        $this->assertSame(0, DB::table('pricing')->count());
    }

    public function testValidTariffCanBeCreatedUpdatedAndDeleted(): void
    {
        $this->postJson('/api/auth/priceVehicles', ['priceVehicles' => [$this->tariff()]])->assertStatus(200);
        $id = DB::table('pricing')->value('id');
        $this->postJson('/api/auth/priceVehicles', ['priceVehicles' => [array_merge($this->tariff(), ['id' => $id, 'price' => 120000])]])->assertStatus(200);
        $this->assertEquals(120000, DB::table('pricing')->value('price'));
        $this->deleteJson('/api/auth/priceVehicles/'.$id)->assertStatus(200);
        $this->assertSame(0, DB::table('pricing')->count());
    }
}
