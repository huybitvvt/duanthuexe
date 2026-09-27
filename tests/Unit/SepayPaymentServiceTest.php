<?php

namespace Tests\Unit;

use App\Http\Services\SepayPaymentService;
use App\Models\Bank;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\SepayPaymentRequest;
use App\Models\SepayWebhookEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SepayPaymentServiceTest extends TestCase
{
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('banks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('bank_name');
            $table->unsignedBigInteger('store_id')->default(0);
            $table->string('account_number');
            $table->string('owner_name');
            $table->string('status')->default('Active');
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('type');
            $table->integer('value');
            $table->text('note');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('cash_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->integer('payment_method');
            $table->string('object_name')->nullable();
            $table->string('object_type')->nullable();
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id');
            $table->string('order_status')->default('renting');
            $table->boolean('created_without_collect_deposit')->default(false);
            $table->boolean('created_without_collect_rental_fees')->default(false);
            $table->boolean('deposit_closed')->default(false);
            $table->integer('pid')->default(0);
            $table->integer('total')->default(0);
            $table->integer('out_dated_at')->default(0);
            $table->integer('outdate_or_early_amount')->default(0);
            $table->integer('first_deposit_amount')->default(0);
            $table->integer('total_rental_fees')->default(0);
            $table->integer('additional_deposit_amount')->default(0);
            $table->text('first_deposit_payment_method')->nullable();
            $table->text('total_rental_payment_method')->nullable();
            $table->text('additional_deposit_payment_method')->nullable();
            $table->dateTime('deposit_contract_created_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->dateTime('return_at');
            $table->dateTime('completed_at')->nullable();
            $table->integer('total_renewal_amount')->default(0);
            $table->integer('handler_price')->default(1);
            $table->integer('minute_out_date')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        require_once __DIR__ . '/../../database/migrations/2026_09_27_000001_create_sepay_payment_tables.php';
        (new \CreateSepayPaymentTables())->up();

        $bank = Bank::create([
            'bank_name' => 'MBBank',
            'store_id' => 0,
            'account_number' => '1234567890',
            'owner_name' => 'HIMOTO',
            'status' => 'Active',
        ]);
        config()->set('services.sepay', [
            'bank_id' => $bank->id,
            'account_number' => '1234567890',
            'webhook_api_key' => 'unit-test-sepay-secret',
        ]);
        $this->user = new User();
        $this->user->id = 7;
        $this->user->role_id = 1;
        $this->user->store_id = 2;
        $this->service = new SepayPaymentService();
    }

    public function test_qr_and_incoming_credits_update_a_generic_receipt_exactly_once(): void
    {
        $payment = $this->service->createRequest(['store_id' => 2, 'amount' => 100000, 'note' => 'Thu dịch vụ'], $this->user);
        $this->assertRegExp('/^HMT[A-Z0-9]{10}$/', $payment['code']);
        $this->assertStringContainsString('amount=100000', $payment['qr_url']);
        $this->assertStringContainsString('des=' . $payment['code'], $payment['qr_url']);
        $this->assertSame(0, Transaction::count());

        $first = $this->payload(9001, $payment['code'], 40000);
        $this->assertSame('matched', $this->service->processWebhook($first)['status']);
        $this->assertSame('duplicate', $this->service->processWebhook($first)['status']);
        $this->assertSame('partial', SepayPaymentRequest::first()->status);
        $this->assertSame(40000, (int) SepayPaymentRequest::first()->received_amount);
        $this->assertSame(1, Transaction::count());

        $second = $this->payload(9002, $payment['code'], 60000);
        $this->service->processWebhook($second);
        $this->assertSame('paid', SepayPaymentRequest::first()->status);
        $this->assertSame(100000, (int) SepayPaymentRequest::first()->received_amount);
        $this->assertSame(100000, (int) Transaction::sum('value'));
        $this->assertSame(2, SepayWebhookEvent::where('status', 'matched')->count());

        $third = $this->payload(9003, $payment['code'], 10000);
        $this->service->processWebhook($third);
        $this->assertSame('overpaid', SepayPaymentRequest::first()->status);
        $this->assertSame(110000, (int) Transaction::sum('value'));
        $this->assertSame('receipt', Transaction::first()->name);
        $this->assertSame(2, (int) Transaction::first()->payment_method);
        $this->assertSame(2, (int) Transaction::first()->store_id);
    }

    public function test_unknown_code_is_held_for_reconciliation_without_credit(): void
    {
        $result = $this->service->processWebhook($this->payload(9004, 'HMTFFFFFFFFFF', 50000));
        $this->assertSame('unmatched', $result['status']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(1, SepayWebhookEvent::where('status', 'unmatched')->count());
    }

    public function test_code_can_be_read_from_transfer_content_when_sepay_does_not_extract_it(): void
    {
        $payment = $this->service->createRequest(['store_id' => 2, 'amount' => 50000, 'note' => 'Thu dịch vụ'], $this->user);
        $payload = $this->payload(9008, $payment['code'], 50000);
        $payload['code'] = null;
        $payload['content'] = 'Chuyen khoan ' . $payment['code'] . ' tai MB';

        $this->assertSame('matched', $this->service->processWebhook($payload)['status']);
        $this->assertSame(50000, (int) Transaction::sum('value'));
    }

    public function test_wrong_account_cannot_post_money_and_outgoing_transfer_is_ignored(): void
    {
        $payment = $this->service->createRequest(['store_id' => 2, 'amount' => 100000, 'note' => 'Thu dịch vụ'], $this->user);
        $out = $this->payload(9005, $payment['code'], 100000);
        $out['transferType'] = 'out';
        $this->assertSame('ignored', $this->service->processWebhook($out)['status']);
        $this->assertSame(0, Transaction::count());

        $wrong = $this->payload(9006, $payment['code'], 100000);
        $wrong['accountNumber'] = '9999999999';
        try {
            $this->service->processWebhook($wrong);
            $this->fail('Wrong account should fail before recording an event.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, Transaction::count());
            $this->assertSame(0, SepayWebhookEvent::where('sepay_transaction_id', 9006)->count());
        }
    }

    public function test_webhook_key_must_match_the_server_secret(): void
    {
        $this->assertTrue($this->service->verifyWebhookKey('Apikey unit-test-sepay-secret'));
        $this->assertFalse($this->service->verifyWebhookKey('Apikey wrong-secret'));
        $this->assertFalse($this->service->verifyWebhookKey('Bearer unit-test-sepay-secret'));
        $this->assertFalse($this->service->verifyWebhookKey(null));
    }

    public function test_branch_cannot_collect_to_another_branch_bank_account(): void
    {
        Bank::first()->update(['store_id' => 3]);
        $this->expectException(ValidationException::class);
        $this->service->createRequest(['store_id' => 2, 'amount' => 100000, 'note' => 'Thu dịch vụ'], $this->user);
    }

    public function test_webhook_endpoint_rejects_wrong_key_before_any_ledger_write(): void
    {
        $payment = $this->service->createRequest(['store_id' => 2, 'amount' => 100000, 'note' => 'Thu dịch vụ'], $this->user);
        $payload = $this->payload(9007, $payment['code'], 100000);

        $this->postJson('/api/sepay/webhook', $payload, ['Authorization' => 'Apikey wrong-secret'])
            ->assertStatus(401);
        $this->assertSame(0, SepayWebhookEvent::count());
        $this->assertSame(0, Transaction::count());

        $this->postJson('/api/sepay/webhook', $payload, ['Authorization' => 'Apikey unit-test-sepay-secret'])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'matched']);
        $this->assertSame(1, Transaction::count());
    }

    public function test_deposit_is_allocated_only_when_not_previously_booked_and_overpay_is_separate(): void
    {
        $order = Order::create(['store_id' => 2, 'created_without_collect_deposit' => true]);
        $payment = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'deposit', 'order_id' => $order->id,
            'amount' => 100000, 'note' => 'Cọc hợp đồng',
        ], $this->user);
        $this->service->processWebhook($this->payload(9010, $payment['code'], 40000));
        $this->assertSame(40000, (int) $order->fresh()->first_deposit_amount);
        $this->assertTrue((bool) $order->fresh()->created_without_collect_deposit);
        $this->service->processWebhook($this->payload(9011, $payment['code'], 70000));
        $this->assertSame(100000, (int) $order->fresh()->first_deposit_amount);
        $this->assertSame(100000, (int) $order->fresh()->pid);
        $this->assertFalse((bool) $order->fresh()->created_without_collect_deposit);
        $this->assertSame(10000, (int) Transaction::where('name', 'receipt')->sum('value'));
        $this->assertSame(100000, (int) Transaction::where('object_name', 'first_deposit')->sum('value'));
        $this->assertSame('overpaid', SepayPaymentRequest::first()->status);
        $this->assertSame(3, (int) SepayWebhookEvent::where('sepay_transaction_id', 9011)->first()->excess_transaction_id);
    }

    public function test_rental_allocation_does_not_duplicate_an_existing_booking(): void
    {
        $order = Order::create(['store_id' => 2, 'created_without_collect_rental_fees' => false, 'total_rental_fees' => 100000, 'total' => 100000]);
        $this->expectException(ValidationException::class);
        $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'rental', 'order_id' => $order->id,
            'amount' => 100000, 'note' => 'Phí thuê',
        ], $this->user);
    }

    public function test_rental_payment_updates_order_and_stale_manual_booking_goes_to_review(): void
    {
        $order = Order::create(['store_id' => 2, 'created_without_collect_rental_fees' => true, 'total' => 80000]);
        $payment = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'rental', 'order_id' => $order->id,
            'amount' => 80000, 'note' => 'Phí thuê',
        ], $this->user);
        $this->service->processWebhook($this->payload(9012, $payment['code'], 80000));
        $this->assertSame(80000, (int) $order->fresh()->total_rental_fees);
        $this->assertFalse((bool) $order->fresh()->created_without_collect_rental_fees);
        $this->assertSame(80000, (int) Transaction::where('name', 'order:rental_fees')->sum('value'));

        $other = Order::create(['store_id' => 2, 'created_without_collect_rental_fees' => true, 'total' => 90000]);
        $pending = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'rental', 'order_id' => $other->id,
            'amount' => 90000, 'note' => 'Phí thuê khác',
        ], $this->user);
        Transaction::create(['name' => 'order:rental_fees', 'type' => 'in', 'value' => 90000,
            'note' => 'Thu tay', 'user_id' => 7, 'store_id' => 2, 'payment_method' => 2,
            'order_id' => $other->id]);
        $this->assertSame('review', $this->service->processWebhook($this->payload(9013, $pending['code'], 90000))['status']);
        $this->assertSame(1, Transaction::where('order_id', $other->id)->count());
        $this->assertSame(1, Transaction::where('name', 'receipt')->count());
        $this->assertSame(0, (int) $other->fresh()->total_rental_fees);
    }

    public function test_extension_changes_return_date_only_after_full_payment(): void
    {
        $order = Order::create(['store_id' => 2]);
        $item = OrderVehicleDetail::create(['order_id' => $order->id, 'return_at' => '2026-10-01 12:00:00']);
        $payment = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'extension', 'order_id' => $order->id,
            'line_item_id' => $item->id, 'extension_return_at' => '2026-10-03 12:00:00',
            'amount' => 50000, 'note' => 'Gia hạn xe',
        ], $this->user);
        $this->service->processWebhook($this->payload(9014, $payment['code'], 20000));
        $this->assertSame('2026-10-01 12:00:00', $item->fresh()->return_at);
        $this->service->processWebhook($this->payload(9015, $payment['code'], 30000));
        $this->assertSame('2026-10-03 12:00:00', $item->fresh()->return_at);
        $this->assertSame(50000, (int) $item->fresh()->total_renewal_amount);
        $this->assertSame(50000, (int) Transaction::where('type', 'addon')->sum('value'));
    }

    public function test_additional_deposit_is_allocated_to_its_own_order_field(): void
    {
        $order = Order::create(['store_id' => 2]);
        $payment = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'additional_deposit', 'order_id' => $order->id,
            'amount' => 30000, 'note' => 'Cọc thêm',
        ], $this->user);
        $this->service->processWebhook($this->payload(9016, $payment['code'], 30000));
        $this->assertSame(30000, (int) $order->fresh()->additional_deposit_amount);
        $this->assertSame(30000, (int) Transaction::where('name', 'order:additional_deposit')->sum('value'));
        $second = $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'additional_deposit', 'order_id' => $order->id,
            'amount' => 20000, 'note' => 'Cọc thêm lần hai',
        ], $this->user);
        $this->service->processWebhook($this->payload(9017, $second['code'], 20000));
        $this->assertSame(50000, (int) $order->fresh()->additional_deposit_amount);
    }

    public function test_rental_qr_amount_must_match_uncollected_contract_fee(): void
    {
        $order = Order::create(['store_id' => 2, 'created_without_collect_rental_fees' => true, 'total' => 100000]);
        $this->expectException(ValidationException::class);
        $this->service->createRequest([
            'store_id' => 2, 'purpose' => 'rental', 'order_id' => $order->id,
            'amount' => 10000, 'note' => 'Phí thuê',
        ], $this->user);
    }

    private function payload(int $id, string $code, int $amount): array
    {
        return [
            'id' => $id,
            'gateway' => 'MBBank',
            'accountNumber' => '1234567890',
            'transferType' => 'in',
            'transferAmount' => $amount,
            'code' => $code,
            'content' => $code . ' chuyen tien',
            'referenceCode' => 'FT' . $id,
        ];
    }
}
