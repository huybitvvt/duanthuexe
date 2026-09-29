<?php

use App\Support\HimotoStores;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize the warehouse catalogue to the six locations approved for
 * HIMOTO.  Existing rows are reused where possible so orders and vehicles do
 * not receive new store IDs merely because an address was corrected.
 *
 * Rows outside the catalogue are removed only when they have no references.
 * Referenced legacy rows are retained as inactive (and are excluded by the
 * application catalogue scope) so financial/order history is never orphaned.
 */
class LockHimotoWarehouseCatalog extends Migration
{
    private const LEGACY_CODES = [
        'LANG' => 'CS1',
        'NGUYEN_HOANG' => 'CS2',
        'HANG_BUT' => 'CS3',
        'GIAP_BAT' => 'CS4',
        'QUANG_TRUNG_HA_DONG' => 'CS5',
        'ONLINE_OWNERSHIP' => 'CS6',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('stores')) {
            return;
        }

        if (!Schema::hasColumn('stores', 'code')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('code', 64)->nullable()->index();
            });
        }
        if (!Schema::hasColumn('stores', 'kind')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('kind', 32)->nullable()->index();
            });
        }

        DB::transaction(function () {
            $canonicalIds = [];
            foreach (HimotoStores::DEFINITIONS as $code => $definition) {
                $lookupCodes = array_merge([$code], array_keys(array_filter(
                    self::LEGACY_CODES,
                    static fn (string $mappedCode): bool => $mappedCode === $code
                )));

                $store = DB::table('stores')
                    ->whereIn('code', $lookupCodes)
                    ->orderByRaw("CASE WHEN code = ? THEN 0 ELSE 1 END", [$code])
                    ->orderBy('id')
                    ->first();

                if (!$store) {
                    $store = DB::table('stores')
                        ->where('store_name', $definition['store_name'])
                        ->orderBy('id')
                        ->first();
                }

                $payload = [
                    'code' => $definition['code'],
                    'store_name' => $definition['store_name'],
                    'store_address' => $definition['store_address'],
                    'kind' => $definition['kind'],
                    'status' => 'opening',
                    'updated_at' => now(),
                ];

                if ($store) {
                    DB::table('stores')->where('id', $store->id)->update($payload);
                    $canonicalIds[] = (int) $store->id;
                } else {
                    $canonicalIds[] = (int) DB::table('stores')->insertGetId(array_merge($payload, [
                        'created_at' => now(),
                    ]));
                }
            }

            $extraStores = DB::table('stores')->whereNotIn('id', $canonicalIds)->get(['id']);
            foreach ($extraStores as $extraStore) {
                $id = (int) $extraStore->id;
                if ($this->hasReferences($id)) {
                    DB::table('stores')->where('id', $id)->update([
                        'status' => 'inactive',
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('stores')->where('id', $id)->delete();
                }
            }
        });
    }

    /**
     * Check all known store-bearing tables before deleting a legacy row.
     * Tables/columns are checked dynamically because old deployments do not
     * have every operational module yet.
     */
    private function hasReferences(int $storeId): bool
    {
        $references = [
            ['users', 'store_id'], ['vehicles', 'store_id'], ['vehicles', 'current_store_id'],
            ['orders', 'store_id'], ['transactions', 'store_id'], ['banks', 'store_id'],
            ['cash', 'store_id'], ['leads', 'store_id'], ['sell_orders', 'store_id'],
            ['lease_contracts', 'store_id'], ['lease_ownership_requests', 'store_id'],
            ['daily_cash_registers', 'store_id'], ['staff_profiles', 'store_id'],
            ['store_duty_schedules', 'store_id'], ['staff_attendances', 'store_id'],
            ['vehicle_transfers', 'from_store_id'], ['vehicle_transfers', 'to_store_id'],
            ['vehicle_location_events', 'from_store_id'], ['vehicle_location_events', 'to_store_id'],
            ['accounting_vat_documents', 'store_id'], ['business_assets', 'store_id'],
            ['accounting_accounts', 'store_id'], ['accounting_journal_entries', 'store_id'],
            ['accounting_journal_lines', 'store_id'], ['audit_events', 'store_id'],
        ];

        foreach ($references as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $storeId)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        // The catalogue migration intentionally does not recreate deleted
        // stores or reactivate legacy rows; restoring a DB backup is the safe
        // rollback for a data-catalogue change.
    }
}
