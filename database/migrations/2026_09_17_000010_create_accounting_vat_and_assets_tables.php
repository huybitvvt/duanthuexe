<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingVatAndAssetsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('accounting_vat_documents')) {
            Schema::create('accounting_vat_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('document_type', 20)->index();
                $table->string('invoice_number', 100);
                $table->date('invoice_date')->index();
                $table->string('counterparty_name', 200);
                $table->string('tax_code', 50)->nullable();
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('payment_status', 30)->default('unpaid');
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->unsignedBigInteger('transaction_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['document_type', 'invoice_number'], 'vat_document_number_unique');
            });
        }

        if (!Schema::hasTable('business_assets')) {
            Schema::create('business_assets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('asset_code', 60)->unique();
                $table->string('name', 200);
                $table->string('category', 100)->nullable();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_cost', 15, 2)->default(0);
                $table->decimal('residual_value', 15, 2)->default(0);
                $table->unsignedInteger('depreciation_months')->default(0);
                $table->string('status', 30)->default('active');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('business_assets');
        Schema::dropIfExists('accounting_vat_documents');
    }
}
