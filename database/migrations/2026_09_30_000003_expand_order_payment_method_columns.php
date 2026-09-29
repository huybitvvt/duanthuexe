<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandOrderPaymentMethodColumns extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        $driver = DB::getDriverName();
        foreach (['first_deposit_payment_method', 'total_rental_payment_method', 'additional_deposit_payment_method'] as $column) {
            if (!Schema::hasColumn('orders', $column)) {
                continue;
            }
            // Payment details include split cash/bank amounts and transaction IDs;
            // the old VARCHAR(191) silently became too short for a normal receipt.
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "orders" ALTER COLUMN "' . $column . '" TYPE TEXT');
            } elseif ($driver === 'mysql') {
                DB::statement('ALTER TABLE `orders` MODIFY COLUMN `' . $column . '` TEXT NULL');
            }
            // SQLite does not enforce a VARCHAR length, so its column needs no change.
        }
    }

    public function down()
    {
        // Do not truncate payment details back to 191 characters.
    }
}
