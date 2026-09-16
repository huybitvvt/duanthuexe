<?php

require_once __DIR__ . '/lib/supabase_pdo.php';

$requirements = [
    'lease_payment_allocations' => [
        'status', 'reversal_transaction_id', 'reversal_reason', 'reversed_at', 'reversed_by',
    ],
    'lease_contracts' => ['discount_amount', 'settled_at'],
    'daily_cash_registers' => [
        'other_expense_bank_personal', 'other_income_bank_personal',
        'other_expense_bank_company', 'other_income_bank_company',
    ],
    'staff_attendances' => [
        'staff_id', 'store_id', 'attendance_date', 'clock_in_at',
        'clock_out_at', 'work_minutes', 'status',
    ],
    'leads' => [
        'source_channel', 'campaign_name', 'utm_source', 'utm_campaign',
    ],
    'accounting_vat_documents' => [
        'document_type', 'invoice_number', 'invoice_date', 'amount_before_tax',
        'vat_rate', 'vat_amount', 'total_amount', 'store_id',
    ],
    'business_assets' => [
        'asset_code', 'name', 'store_id', 'purchase_cost', 'residual_value',
        'depreciation_months', 'status',
    ],
];
$requiredMigrations = [
    '2026_09_16_000006_add_reversal_fields_to_lease_payment_allocations_table',
    '2026_09_16_000007_add_bank_expenses_to_daily_cash_registers_table',
    '2026_09_16_000008_create_staff_attendances_table',
    '2026_09_16_000009_add_attribution_to_leads_table',
    '2026_09_17_000010_create_accounting_vat_and_assets_tables',
];
$checksumManifestPath = dirname(__DIR__) . '/database/migrations/operational-checksums.json';
$approvedChecksums = is_file($checksumManifestPath)
    ? json_decode(file_get_contents($checksumManifestPath), true)
    : [];

$json = in_array('--json', $argv, true);
$schema = getenv('DB_SCHEMA') ?: 'himoto';
if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
    fwrite(STDERR, "[ERROR] Invalid DB_SCHEMA.\n");
    exit(1);
}

try {
    $pdo = himoto_supabase_pdo();
    $statement = $pdo->prepare(
        'SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = :schema'
    );
    $statement->execute([':schema' => $schema]);
    $available = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $available[$row['table_name']][$row['column_name']] = true;
    }

    $missing = [];
    foreach ($requirements as $table => $columns) {
        foreach ($columns as $column) {
            if (empty($available[$table][$column])) {
                $missing[] = $table . '.' . $column;
            }
        }
    }

    $pendingMigrations = $requiredMigrations;
    if (!empty($available['migrations']['migration'])) {
        $applied = $pdo->query('SELECT migration FROM ' . $schema . '.migrations')->fetchAll(PDO::FETCH_COLUMN);
        $pendingMigrations = array_values(array_filter($requiredMigrations, function ($migration) use ($applied) {
            return !in_array($migration, $applied, true);
        }));
    }

    $migrationChecksums = [];
    $checksumMismatches = [];
    foreach ($requiredMigrations as $migration) {
        $path = dirname(__DIR__) . '/database/migrations/' . $migration . '.php';
        $migrationChecksums[$migration] = is_file($path) ? hash_file('sha256', $path) : null;
        $approved = $approvedChecksums[$migration] ?? null;
        if (!$approved || !hash_equals($approved, (string) $migrationChecksums[$migration])) {
            $checksumMismatches[] = $migration;
        }
    }

    $result = [
        'ready' => $missing === [] && $pendingMigrations === [] && $checksumMismatches === [],
        'schema' => $schema,
        'missing_columns' => $missing,
        'pending_migrations' => $pendingMigrations,
        'migration_checksums_sha256' => $migrationChecksums,
        'checksum_mismatches' => $checksumMismatches,
    ];

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo $result['ready'] ? "[OK] Operational schema is ready.\n" : "[BLOCKED] Operational schema is incomplete.\n";
        foreach ($missing as $item) {
            echo "  missing: {$item}\n";
        }
        foreach ($pendingMigrations as $item) {
            echo "  pending: {$item}\n";
        }
        foreach ($checksumMismatches as $item) {
            echo "  checksum mismatch: {$item}\n";
        }
    }

    exit($result['ready'] ? 0 : 2);
} catch (Throwable $exception) {
    $result = [
        'ready' => false,
        'error' => 'Unable to verify the database schema.',
        'exception' => get_class($exception),
    ];
    fwrite(STDERR, $json ? json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL : "[ERROR] {$result['error']}\n");
    exit(1);
}
