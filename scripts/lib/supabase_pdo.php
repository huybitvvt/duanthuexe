<?php

/**
 * Build a PDO connection without echoing credentials. Supports both PDO DSNs
 * and Supabase postgres:// / postgresql:// connection URLs.
 */
function himoto_supabase_pdo(): PDO
{
    $databaseUrl = getenv('SUPABASE_DATABASE_URL') ?: getenv('DATABASE_URL');
    $host = getenv('SUPABASE_DB_HOST') ?: getenv('DB_HOST');
    $port = getenv('SUPABASE_DB_PORT') ?: getenv('DB_PORT') ?: 5432;
    $database = getenv('SUPABASE_DB_DATABASE') ?: getenv('DB_DATABASE') ?: 'postgres';
    $username = getenv('SUPABASE_DB_USERNAME') ?: getenv('DB_USERNAME');
    $password = getenv('SUPABASE_DB_PASSWORD') ?: getenv('DB_PASSWORD');

    if ($databaseUrl) {
        if (strpos($databaseUrl, 'pgsql:') === 0) {
            $dsn = $databaseUrl;
        } else {
            $parts = parse_url($databaseUrl);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)) {
                throw new RuntimeException('SUPABASE_DATABASE_URL must be a pgsql DSN or postgres URL.');
            }

            $host = $parts['host'] ?? null;
            $port = $parts['port'] ?? 5432;
            $database = ltrim($parts['path'] ?? '/postgres', '/');
            $username = isset($parts['user']) ? rawurldecode($parts['user']) : $username;
            $password = isset($parts['pass']) ? rawurldecode($parts['pass']) : $password;

            parse_str($parts['query'] ?? '', $query);
            $sslMode = $query['sslmode'] ?? 'require';
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}";
        }
    } else {
        if (!$host || !$username || !$password) {
            throw new RuntimeException('Missing Supabase database credentials.');
        }
        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";
    }

    return new PDO($dsn, $username ?: null, $password ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 15,
    ]);
}
