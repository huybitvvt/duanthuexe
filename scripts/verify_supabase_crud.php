<?php
/**
 * Supabase Real Database CRUD Verification Script
 * Reads credentials strictly from environment variables.
 * Implements canary test verification with test_run_id, checksum validation, and targeted cleanup in finally block.
 * Usage: SUPABASE_DATABASE_URL="pgsql:..." php scripts/verify_supabase_crud.php
 */

require_once __DIR__ . '/lib/supabase_pdo.php';
$testRunId = 'canary_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

echo "=== HIMOTO SUPABASE CRUD VERIFICATION ===\n";
echo "Test Run ID: {$testRunId}\n";
echo "Connecting to Supabase PostgreSQL...\n";

$pdo = null;

try {
    $pdo = himoto_supabase_pdo();
    echo "[OK] Connected to Supabase PostgreSQL.\n";

    // 1. Ensure canary verification table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS canary_verification_runs (
            id SERIAL PRIMARY KEY,
            test_run_id VARCHAR(100) NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            payload_checksum VARCHAR(64) NOT NULL,
            record_data JSONB NOT NULL,
            status VARCHAR(50) NOT NULL,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_canary_test_run_id ON canary_verification_runs(test_run_id);
    ");
    echo "[OK] Verified/created table 'canary_verification_runs'.\n";

    // Canonical hashing function that sorts keys to handle JSONB normalization
    function canonicalHash(array $data): string {
        ksort($data);
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // 2. Test INSERT (Create)
    echo "\n--- STEP 1: CREATE (INSERT CANARY RECORD) ---\n";
    $initialPayload = [
        'action' => 'contract_create',
        'amount' => 1500000,
        'contract_number' => '20260916-0001',
        'customer_name' => 'Nguyễn Văn Kiểm Thử',
        'phone' => '0987654321',
        'vehicle_plate' => '29A-99999',
    ];
    $checksum1 = canonicalHash($initialPayload);

    $stmt = $pdo->prepare("
        INSERT INTO canary_verification_runs (test_run_id, module_name, payload_checksum, record_data, status)
        VALUES (:test_run_id, 'lease_contracts', :checksum, :record_data, 'pending')
        RETURNING id, created_at;
    ");
    $stmt->execute([
        ':test_run_id' => $testRunId,
        ':checksum' => $checksum1,
        ':record_data' => json_encode($initialPayload, JSON_UNESCAPED_UNICODE),
    ]);
    $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
    $recordId = (int)$inserted['id'];
    echo "[OK] Created record ID: {$recordId} at {$inserted['created_at']}\n";
    echo "     Canonical Checksum: {$checksum1}\n";

    // 3. Test SELECT (Read & Verify Integrity)
    echo "\n--- STEP 2: READ (VERIFY FROM DATABASE AFTER INSERT) ---\n";
    $stmt = $pdo->prepare("
        SELECT id, test_run_id, module_name, payload_checksum, record_data, status, created_at
        FROM canary_verification_runs
        WHERE id = :id AND test_run_id = :test_run_id;
    ");
    $stmt->execute([':id' => $recordId, ':test_run_id' => $testRunId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("Record with ID {$recordId} could not be read back!");
    }
    $decodedData = json_decode($record['record_data'], true);
    $readChecksum = canonicalHash($decodedData);

    if ($readChecksum !== $checksum1) {
        throw new Exception("Checksum mismatch! Inserted: {$checksum1}, Read: {$readChecksum}");
    }
    echo "[OK] Read record verified from Supabase. Data matches SHA-256 checksum exactly.\n";
    echo "     Contract: {$decodedData['contract_number']} | Customer: {$decodedData['customer_name']} | Phone: {$decodedData['phone']}\n";

    // 4. Test UPDATE (Modify & Audit)
    echo "\n--- STEP 3: UPDATE (MODIFY RECORD WITH REASON & AUDIT) ---\n";
    $updatedPayload = array_merge($decodedData, [
        'action' => 'contract_early_settle',
        'settlement_note' => 'Tất toán sớm toàn bộ hợp đồng',
        'settlement_amount' => 12000000,
        'settled_at' => date('Y-m-d H:i:s'),
    ]);
    $checksum2 = canonicalHash($updatedPayload);

    $stmt = $pdo->prepare("
        UPDATE canary_verification_runs
        SET record_data = :record_data,
            payload_checksum = :checksum,
            status = 'settled',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND test_run_id = :test_run_id
        RETURNING updated_at;
    ");
    $stmt->execute([
        ':record_data' => json_encode($updatedPayload, JSON_UNESCAPED_UNICODE),
        ':checksum' => $checksum2,
        ':id' => $recordId,
        ':test_run_id' => $testRunId,
    ]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "[OK] Updated record ID: {$recordId} at {$updated['updated_at']}\n";
    echo "     New Checksum: {$checksum2}\n";

    // Re-read to confirm update took effect
    $stmt = $pdo->prepare("SELECT record_data, status, payload_checksum FROM canary_verification_runs WHERE id = :id");
    $stmt->execute([':id' => $recordId]);
    $refreshed = $stmt->fetch(PDO::FETCH_ASSOC);
    $refreshedData = json_decode($refreshed['record_data'], true);
    $refreshedChecksum = canonicalHash($refreshedData);
    if ($refreshedChecksum !== $checksum2) {
        throw new Exception("Checksum mismatch on updated record!");
    }
    echo "[OK] Verified update persisted. New status: '{$refreshed['status']}', Action: '{$refreshedData['action']}'\n";

    // 5. Test DELETE (Safe cleanup scoped to test_run_id only)
    echo "\n--- STEP 4: DELETE (SAFE CLEANUP SCOPED TO CURRENT RUN) ---\n";
    $stmt = $pdo->prepare("DELETE FROM canary_verification_runs WHERE test_run_id = :test_run_id RETURNING id;");
    $stmt->execute([':test_run_id' => $testRunId]);
    $deletedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "[OK] Deleted canary test records for this run: " . implode(', ', $deletedIds) . "\n";

    // Verify 0 rows remain for this test run
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM canary_verification_runs WHERE test_run_id = :test_run_id");
    $stmt->execute([':test_run_id' => $testRunId]);
    $remainingCount = (int)$stmt->fetchColumn();
    if ($remainingCount !== 0) {
        throw new Exception("Cleanup failed! {$remainingCount} rows still remain for test_run_id={$testRunId}.");
    }

    echo "\n=== SUPABASE CANARY VERIFICATION PASSED (NO SECRETS HARDCODED, NO UNBOUNDED TRUNCATE) ===\n";
    exit(0);

} catch (Exception $e) {
    echo "\n[FAIL] VERIFICATION FAILED: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Safe targeted teardown in finally block in case of early exception
    if ($pdo && isset($testRunId)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM canary_verification_runs WHERE test_run_id = :test_run_id;");
            $stmt->execute([':test_run_id' => $testRunId]);
        } catch (\Throwable $ignored) {
            // Ignore teardown errors on exit
        }
    }
}
