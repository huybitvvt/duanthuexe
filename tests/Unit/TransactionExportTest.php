<?php

namespace Tests\Unit;

use App\Exports\TransactionExport;
use App\Http\Services\TransactionService;
use App\Models\Bank;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('transactions');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('store_name')->nullable();
            $table->timestamps();
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('bank_name')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('store_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
            $table->string('type')->nullable(); // in, out, addon
            $table->integer('payment_method')->nullable(); // 1=cash, 2=bank
            $table->bigInteger('value')->default(0);
            $table->text('note')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function test_transaction_export_collection_and_mapping()
    {
        $user = User::create(['name' => 'Nguyễn Văn A', 'email' => 'a@test.com']);
        $store = Store::create(['store_name' => 'Cơ sở 1 - Cầu Giấy']);
        $bank = Bank::create([
            'bank_name' => 'Vietcombank',
            'owner_name' => 'NGUYEN VAN A',
            'account_number' => '0123456789',
        ]);

        // 1. Transaction Thu qua bank
        $t1 = Transaction::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'bank_id' => $bank->id,
            'type' => 'in',
            'payment_method' => 2,
            'value' => 1500000,
            'note' => 'Thu tiền thuê xe',
            'status' => 'Hoàn thành',
        ]);

        // 2. Transaction Chi tiền mặt
        $t2 = Transaction::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'bank_id' => null,
            'type' => 'out',
            'payment_method' => 1,
            'value' => 300000,
            'note' => 'Chi rửa xe',
            'status' => 'Đã duyệt',
        ]);

        // 3. Transaction Thu phụ phí (không có store_id - kiểm tra leftJoin)
        $t3 = Transaction::create([
            'user_id' => null,
            'store_id' => null,
            'bank_id' => null,
            'type' => 'addon',
            'payment_method' => null,
            'value' => 50000,
            'note' => 'Phụ phí xăng',
            'status' => null,
        ]);

        $export = app()->make(TransactionExport::class, ['params' => []]);
        $collection = $export->collection();

        $this->assertCount(3, $collection, 'Must include all 3 transactions even if store_id or user_id is null');

        // Verify mapped data for item 1 (ordered DESC by id, so t3, t2, t1)
        $mappedItems = $collection->map(function ($item) use ($export) {
            return $export->map($item);
        });

        // Item 1 in query is t3 (id=3)
        $row3 = $mappedItems[0];
        $this->assertEquals('#3', $row3[0]);
        $this->assertEquals('N/A', $row3[2]);
        $this->assertEquals('Toàn hệ thống', $row3[3]);
        $this->assertEquals('Thu phụ phí', $row3[4]);
        $this->assertEquals('Tiền mặt', $row3[5]);
        $this->assertEquals('50.000 đ', $row3[6]);
        $this->assertEquals('Phụ phí xăng', $row3[7]);
        $this->assertEquals('Hoàn thành', $row3[8]);

        // Item 2 in query is t2 (id=2)
        $row2 = $mappedItems[1];
        $this->assertEquals('#2', $row2[0]);
        $this->assertEquals('Nguyễn Văn A', $row2[2]);
        $this->assertEquals('Cơ sở 1 - Cầu Giấy', $row2[3]);
        $this->assertEquals('Chi', $row2[4]);
        $this->assertEquals('Tiền mặt', $row2[5]);
        $this->assertEquals('300.000 đ', $row2[6]);
        $this->assertEquals('Chi rửa xe', $row2[7]);
        $this->assertEquals('Đã duyệt', $row2[8]);

        // Item 3 in query is t1 (id=1)
        $row1 = $mappedItems[2];
        $this->assertEquals('#1', $row1[0]);
        $this->assertEquals('Nguyễn Văn A', $row1[2]);
        $this->assertEquals('Cơ sở 1 - Cầu Giấy', $row1[3]);
        $this->assertEquals('Thu', $row1[4]);
        $this->assertStringContainsString('Chuyển khoản - Vietcombank - NGUYEN VAN A - 0123456789', $row1[5]);
        $this->assertEquals('1.500.000 đ', $row1[6]);
        $this->assertEquals('Thu tiền thuê xe', $row1[7]);
        $this->assertEquals('Hoàn thành', $row1[8]);

        // Headings check
        $headings = $export->headings();
        $this->assertCount(9, $headings);
        $this->assertEquals('Mã giao dịch', $headings[0]);
        $this->assertEquals('Trạng thái', $headings[8]);

        // Title check
        $this->assertEquals('Lịch sử thu chi', $export->title());

        // Test Excel file generation
        Excel::fake();
        Excel::download($export, 'lich-su-thu-chi.xlsx');
        Excel::assertDownloaded('lich-su-thu-chi.xlsx');
    }
}
