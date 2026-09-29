<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TrackRentalPricingScheme extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_vehicle_details') && !Schema::hasColumn('order_vehicle_details', 'pricing_scheme')) {
            Schema::table('order_vehicle_details', function (Blueprint $table) {
                $table->string('pricing_scheme', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Keep the scheme marker so previously agreed rental totals are not re-priced.
    }
}
