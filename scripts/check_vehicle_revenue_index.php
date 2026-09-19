<?php

/**
 * Read-only check of the vehicle revenue index in the HIMOTO schema.
 * Load .env.supabase into the process before running this script.
 */

require_once __DIR__ . '/lib/supabase_pdo.php';

try {
    $pdo = himoto_supabase_pdo();
    $migration = $pdo->query(
        "SELECT batch FROM himoto.migrations WHERE migration = '2026_09_19_000002_add_vehicle_revenue_index'"
    )->fetch(PDO::FETCH_ASSOC);
    $index = $pdo->query(
        "SELECT i.indisvalid, i.indisready, pg_get_indexdef(c.oid) AS definition
         FROM pg_class c
         JOIN pg_namespace n ON n.oid = c.relnamespace
         JOIN pg_index i ON i.indexrelid = c.oid
         WHERE n.nspname = 'himoto' AND c.relname = 'order_vehicle_details_rent_vehicle_idx'"
    )->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'table_exists' => (bool) $pdo->query("SELECT to_regclass('himoto.order_vehicle_details') IS NOT NULL")->fetchColumn(),
        'migration_batch' => $migration ? (int) $migration['batch'] : null,
        'index_exists' => (bool) $index,
        'index_valid' => $index ? in_array($index['indisvalid'], [true, 't', '1', 1], true) : false,
        'index_ready' => $index ? in_array($index['indisready'], [true, 't', '1', 1], true) : false,
        'definition' => $index ? $index['definition'] : null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Index check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
