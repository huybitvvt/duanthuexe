<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDraftReferenceToOrders extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'draft_reference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('draft_reference', 32)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        // Reference numbers can be used to reconcile paper forms; retain them.
    }
}
