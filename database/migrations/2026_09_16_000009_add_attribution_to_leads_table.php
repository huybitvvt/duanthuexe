<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttributionToLeadsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'source_channel')) {
                $table->string('source_channel', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('leads', 'campaign_name')) {
                $table->string('campaign_name', 150)->nullable()->index();
            }
            if (!Schema::hasColumn('leads', 'utm_source')) {
                $table->string('utm_source', 150)->nullable();
            }
            if (!Schema::hasColumn('leads', 'utm_campaign')) {
                $table->string('utm_campaign', 150)->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        foreach (['source_channel', 'campaign_name', 'utm_source', 'utm_campaign'] as $column) {
            if (Schema::hasColumn('leads', $column)) {
                Schema::table('leads', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
}
