<?php
/**
 * Test Supabase PostgreSQL Connection
 * Reads database credentials securely from environment variables.
 * Usage: SUPABASE_DATABASE_URL="pgsql:host=...;port=5432;dbname=..." php scripts/test_supabase_connection.php
 */

require_once __DIR__ . '/lib/supabase_pdo.php';

try {
    echo "Connecting to Supabase PostgreSQL...\n";
    $pdo = himoto_supabase_pdo();

    $stmt = $pdo->query("SELECT current_database(), current_user, version();");
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "CONNECTED SUCCESSFULLY!\n";
    echo "Database: " . htmlspecialchars($info['current_database'], ENT_QUOTES, 'UTF-8') . "\n";
    echo "User: " . htmlspecialchars($info['current_user'], ENT_QUOTES, 'UTF-8') . "\n";
    echo "PostgreSQL Version: " . substr($info['version'], 0, 40) . "...\n";

    // Check existing tables
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in public schema (" . count($tables) . "):\n";
    echo implode(', ', array_slice($tables, 0, 15)) . (count($tables) > 15 ? '...' : '') . "\n";

} catch (Exception $e) {
    echo "CONNECTION FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
