<?php

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'Tests\\') === 0) {
        $relativeClass = substr($class, 6);
        $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

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
