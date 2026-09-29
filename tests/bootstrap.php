<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Local development may share vendor/ via a junction with another checkout.
// Resolve project classes from this checkout before Composer's vendor map so
// tests exercise the actual working tree, not the adjacent project's app/.
spl_autoload_register(function ($class) {
    $prefix = strpos($class, 'Tests\\') === 0 ? 'Tests\\' : (strpos($class, 'App\\') === 0 ? 'App\\' : null);
    if ($prefix === null) {
        return;
    }
    $root = $prefix === 'Tests\\' ? __DIR__ : __DIR__ . '/../app';
    $relativeClass = substr($class, strlen($prefix));
    $file = $root . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}, true, true);

// Guard: Enforce test isolation using SQLite in-memory database
putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';

putenv('DB_DATABASE=:memory:');
$_ENV['DB_DATABASE'] = ':memory:';
$_SERVER['DB_DATABASE'] = ':memory:';

// Prevent connecting to remote production Supabase during testing
putenv('DATABASE_URL=');
$_ENV['DATABASE_URL'] = '';
$_SERVER['DATABASE_URL'] = '';
