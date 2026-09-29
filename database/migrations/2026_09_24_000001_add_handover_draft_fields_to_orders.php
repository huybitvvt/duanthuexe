<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHandoverDraftFieldsToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_mode', 20)->default('standard');
            $table->string('guardian_name', 191)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('guardian_id_card', 32)->nullable();
            $table->json('draft_payload')->nullable();
        });

        // An intake can be saved before the branch is known. Issuing it still
        // requires a branch in OrderValidator.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE "orders" ALTER COLUMN "store_id" DROP NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->bigInteger('store_id')->nullable()->change();
            });
        }
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_mode', 'guardian_name', 'guardian_phone', 'guardian_id_card', 'draft_payload']);
        });
    }
}
