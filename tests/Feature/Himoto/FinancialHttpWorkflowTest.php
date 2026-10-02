<?php

namespace Tests\Feature\Himoto;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialHttpWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('roles', function ($t) { $t->increments('id'); $t->string('slug'); });
        Schema::create('stores', function ($t) { $t->increments('id'); $t->string('store_name'); });
        Schema::create('banks', function ($t) { $t->increments('id'); $t->string('bank_name'); $t->integer('store_id'); });
        Schema::create('cash', function ($t) { $t->increments('id'); $t->integer('store_id'); $t->string('status'); });
        Schema::create('transactions', function ($t) {
            $t->increments('id'); $t->integer('store_id')->nullable(); $t->integer('user_id'); $t->string('name');
            $t->string('type'); $t->decimal('value', 18, 2); $t->integer('bank_id')->nullable(); $t->integer('cash_id')->nullable();
            $t->integer('payment_method'); $t->string('note')->nullable(); $t->integer('order_id')->nullable(); $t->timestamps();
        });
        Schema::create('activity_logs', function ($t) {
            $t->increments('id'); $t->integer('transaction_id')->nullable(); $t->integer('order_id')->nullable();
            $t->integer('user_id')->nullable(); $t->string('name'); $t->string('action'); $t->text('content');
            $t->text('metadata')->nullable(); $t->timestamps();
        });
        DB::table('roles')->insert(['id' => 5, 'slug' => 'ke-toan']);
        DB::table('stores')->insert(['id' => 31, 'store_name' => 'CS1']);
        DB::table('banks')->insert(['id' => 1, 'bank_name' => 'Fixture bank', 'store_id' => 31]);
        DB::table('cash')->insert(['id' => 1, 'store_id' => 31, 'status' => 'Active']);
        $this->withoutMiddleware(\Tymon\JWTAuth\Http\Middleware\Authenticate::class);
        $this->actingAs((new User())->forceFill(['id' => 1, 'role_id' => 1, 'status' => 'active']), 'api');
    }

    private function receipt(): array
    {
        return ['store_id' => 31, 'type' => 'in', 'payment_method' => 3, 'bank_id' => 1,
            'cash_amount' => 100, 'bank_transfer_amount' => 200, 'note' => 'Fixture payment', 'created_at' => '02-10-2026 08:00:00'];
    }

    public function testSplitReceiptIsAtomicWhenSecondInsertFails(): void
    {
        DB::unprepared("CREATE TRIGGER fail_bank_insert BEFORE INSERT ON transactions WHEN NEW.bank_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'fixture storage failure'); END");
        $response = $this->postJson('/api/auth/receipt', $this->receipt());
        $this->assertTrue(in_array($response->status(), [400, 422, 500], true));
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function testSplitReceiptUsesSeparatePaymentMethodsAndCorrectStatistics(): void
    {
        $response = $this->postJson('/api/auth/receipt', $this->receipt());
        $this->assertEquals(200, $response->status(), $response->getContent());
        $this->assertSame(2, Transaction::count());
        $this->assertEquals([1, 2], Transaction::orderBy('id')->pluck('payment_method')->all());
        $this->assertEquals(300, Transaction::sum('value'));
        $stats = app(\App\Http\Services\TransactionService::class)->stats(new \Illuminate\Http\Request(['store_id' => 31]));
        $this->assertEquals(100, $stats['cash_in']);
        $this->assertEquals(200, $stats['banks']->first()->type_in);
    }

    public function testAccountantCanCreateReceiptForChosenStore(): void
    {
        $this->actingAs((new User())->forceFill(['id' => 5, 'role_id' => 5, 'status' => 'active']), 'api');
        $response = $this->postJson('/api/auth/receipt', $this->receipt());
        $this->assertEquals(200, $response->status(), $response->getContent());
        $this->assertEquals([31], Transaction::distinct()->pluck('store_id')->all());
        $this->assertSame(1, (int) Transaction::where('bank_id', null)->value('cash_id'));
    }

    public function testInvalidReceiptIsRejectedBeforeAnyWrites(): void
    {
        foreach ([['payment_method' => 9], ['cash_amount' => -1], ['bank_id' => 999], ['created_at' => 'not-a-date']] as $invalid) {
            $this->postJson('/api/auth/receipt', array_merge($this->receipt(), $invalid))->assertStatus(422);
        }
        $this->assertSame(0, Transaction::count());
    }

    public function testTransactionHttpCreateUpdateDeleteReturnsSuccess(): void
    {
        $payload = ['store_id' => 31, 'user_id' => 1, 'type' => 'in', 'value' => 100, 'payment_method' => 1, 'name' => 'receipt'];
        $this->postJson('/api/auth/transactions', $payload)->assertStatus(200);
        $id = Transaction::first()->id;
        $this->putJson('/api/auth/transactions/'.$id, array_merge($payload, ['value' => 200]))->assertStatus(200);
        $this->assertEquals(200, Transaction::find($id)->value);
        $this->deleteJson('/api/auth/transactions/'.$id)->assertStatus(200);
        $this->assertSame(0, Transaction::count());
    }

    public function testInvalidTransactionAndMissingUpdateTargetDoNotCreateRows(): void
    {
        $this->postJson('/api/auth/transactions', [])->assertStatus(422);
        $payload = ['store_id' => 31, 'user_id' => 1, 'type' => 'in', 'value' => 100, 'payment_method' => 1, 'name' => 'receipt'];
        $this->putJson('/api/auth/transactions/999', $payload)->assertStatus(404);
        $this->assertSame(0, Transaction::count());
    }
}
