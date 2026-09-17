<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDoubleEntryAccountingTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('accounting_accounts')) {
            Schema::create('accounting_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 20)->unique();
                $table->string('name', 150);
                $table->string('type', 30); // asset, liability, equity, revenue, expense
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('normal_balance', 10)->default('debit'); // debit, credit
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });

            // Seed standard accounts
            $accounts = [
                ['code' => '111', 'name' => 'Tiền mặt', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '1111', 'name' => 'Tiền mặt tại quỹ cơ sở', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '112', 'name' => 'Tiền gửi ngân hàng', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '1121', 'name' => 'Tiền gửi ngân hàng công ty', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '131', 'name' => 'Phải thu của khách hàng', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '1311', 'name' => 'Phải thu khách thuê xe', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '1312', 'name' => 'Phải thu hợp đồng thuê sở hữu', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '133', 'name' => 'Thuế GTGT được khấu trừ', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '1331', 'name' => 'Thuế GTGT đầu vào được khấu trừ', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '211', 'name' => 'Tài sản cố định hữu hình', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '2111', 'name' => 'Phương tiện vận tải (Xe máy/Xe điện)', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '214', 'name' => 'Hao mòn tài sản cố định', 'type' => 'asset', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '2141', 'name' => 'Hao mòn lũy kế TSCĐ hữu hình', 'type' => 'asset', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '331', 'name' => 'Phải trả cho người bán', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '333', 'name' => 'Thuế và các khoản phải nộp Nhà nước', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '3331', 'name' => 'Thuế GTGT phải nộp (Đầu ra)', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '338', 'name' => 'Phải trả, phải nộp khác', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '3386', 'name' => 'Tiền nhận ký quỹ, đặt cọc', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '411', 'name' => 'Vốn đầu tư của chủ sở hữu', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '511', 'name' => 'Doanh thu bán hàng và cung cấp dịch vụ', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '5111', 'name' => 'Doanh thu cho thuê xe ngắn hạn', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '5112', 'name' => 'Doanh thu cho thuê xe dài hạn / thuê sở hữu', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '5113', 'name' => 'Doanh thu phụ trội, phạt, phát sinh', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '642', 'name' => 'Chi phí quản lý doanh nghiệp / vận hành', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '6421', 'name' => 'Chi phí khấu hao TSCĐ', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '6422', 'name' => 'Chi phí sửa chữa, bảo dưỡng xe', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '6428', 'name' => 'Chi phí vận hành khác', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '711', 'name' => 'Thu nhập khác', 'type' => 'revenue', 'normal_balance' => 'credit', 'parent_id' => null],
                ['code' => '811', 'name' => 'Chi phí khác', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_id' => null],
                ['code' => '911', 'name' => 'Xác định kết quả kinh doanh', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_id' => null],
            ];

            $now = date('Y-m-d H:i:s');
            foreach ($accounts as $acc) {
                $acc['created_at'] = $now;
                $acc['updated_at'] = $now;
                DB::table('accounting_accounts')->insert($acc);
            }
        }

        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('fiscal_year')->index();
                $table->integer('period_month')->index();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 20)->default('open'); // open, closing, closed
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->unsignedBigInteger('reopened_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['fiscal_year', 'period_month'], 'fiscal_period_unique');
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('entry_number', 50)->unique();
                $table->date('entry_date')->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('status', 20)->default('posted'); // draft, posted, reversed
                $table->string('description', 500);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('reversed_entry_id')->nullable()->index();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->timestamps();

                $table->index(['source_type', 'source_id'], 'journal_source_index');
            });
        }

        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->string('description', 500)->nullable();
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_reconciliations')) {
            Schema::create('accounting_reconciliations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('period_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->string('account_type', 20); // cash, bank
                $table->date('reconciliation_date')->index();
                $table->decimal('book_balance', 15, 2)->default(0);
                $table->decimal('actual_balance', 15, 2)->default(0);
                $table->decimal('difference', 15, 2)->default(0);
                $table->string('status', 30)->default('pending'); // pending, matched, discrepancy, approved
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('accounting_reconciliations');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_accounts');
    }
}
