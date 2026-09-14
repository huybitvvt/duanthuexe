<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunTestsCommand extends Command
{
    protected $signature = 'test {--filter= : Filter which tests to run}';
    protected $description = 'Run the application tests via PHPUnit';

    public function handle()
    {
        $php = PHP_BINARY;
        $configured = getenv('HIMOTO_PHPUNIT_PATH');
        $candidates = array_filter([
            $configured ?: null,
            base_path('vendor/bin/phpunit'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'phpunit.phar',
        ]);
        $phpunit = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $phpunit = $candidate;
                break;
            }
        }
        if (!$phpunit) {
            $this->error('PHPUnit not found. Set HIMOTO_PHPUNIT_PATH or install vendor/bin/phpunit.');
            return 1;
        }

        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($phpunit);
        if ($filter = $this->option('filter')) {
            $cmd .= ' --filter ' . escapeshellarg($filter);
        }

        passthru($cmd, $exitCode);
        return $exitCode;
    }
}
