<?php
/**
 * Test Supabase PostgreSQL Connection
 * Reads database credentials securely from environment variables.
 * Usage: SUPABASE_DATABASE_URL="pgsql:host=...;port=5432;dbname=..." php scripts/test_supabase_connection.php
 */

$dbUrl = getenv('SUPABASE_DATABASE_URL');
$host = getenv('SUPABASE_DB_HOST');
$port = getenv('SUPABASE_DB_PORT') ?: 5432;
$dbname = getenv('SUPABASE_DB_DATABASE') ?: 'postgres';
$user = getenv('SUPABASE_DB_USERNAME');
$password = getenv('SUPABASE_DB_PASSWORD');

if (!$dbUrl && (!$host || !$user || !$password)) {
    fwrite(STDERR, "[ERROR] Missing Supabase database credentials.\n");
    fwrite(STDERR, "Please provide SUPABASE_DATABASE_URL or (SUPABASE_DB_HOST, SUPABASE_DB_USERNAME, SUPABASE_DB_PASSWORD) as environment variables.\n");
    exit(1);
}

$dsn = $dbUrl ?: "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

try {
    echo "Connecting to Supabase PostgreSQL...\n";
    $pdo = new PDO($dsn, $user ?: null, $password ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);

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
