<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentSnapshotToLeaseContractsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('lease_contracts')) {
            Schema::table('lease_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('lease_contracts', 'document_snapshot')) {
                    $table->longText('document_snapshot')->nullable();
                }
                if (!Schema::hasColumn('lease_contracts', 'document_snapshot_hash')) {
                    $table->string('document_snapshot_hash', 64)->nullable()->index();
                }
                if (!Schema::hasColumn('lease_contracts', 'document_snapshot_version')) {
                    $table->string('document_snapshot_version', 20)->default('1.0');
                }
                if (!Schema::hasColumn('lease_contracts', 'document_snapshot_locked_at')) {
                    $table->timestamp('document_snapshot_locked_at')->nullable();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('lease_contracts')) {
            Schema::table('lease_contracts', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('lease_contracts', 'document_snapshot_locked_at')) {
                    $cols[] = 'document_snapshot_locked_at';
                }
                if (Schema::hasColumn('lease_contracts', 'document_snapshot_version')) {
                    $cols[] = 'document_snapshot_version';
                }
                if (Schema::hasColumn('lease_contracts', 'document_snapshot_hash')) {
                    $cols[] = 'document_snapshot_hash';
                }
                if (Schema::hasColumn('lease_contracts', 'document_snapshot')) {
                    $cols[] = 'document_snapshot';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
}
