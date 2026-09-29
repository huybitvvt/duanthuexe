<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowDraftCustomersWithoutIdCard extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'id_card')) {
            // Doctrine DBAL cannot initialize its statement class on a persistent
            // PDO connection. Use native PostgreSQL DDL in production and keep
            // the schema-builder path for SQLite-based tests.
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                Schema::getConnection()->statement('ALTER TABLE "customers" ALTER COLUMN "id_card" DROP NOT NULL');
            } else {
                Schema::table('customers', function (Blueprint $table) {
                    // SQL UNIQUE permits multiple NULLs while keeping genuine CCCDs unique.
                    $table->string('id_card')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Draft customers may still lack a CCCD. Requiring NOT NULL would fail
        // until they have been completed or migrated separately.
    }
}
