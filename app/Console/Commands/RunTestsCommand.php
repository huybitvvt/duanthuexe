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
        $phpunit = 'E:\\duanthuexe\\tools\\phpunit.phar';
        if (!file_exists($phpunit)) {
            $phpunit = base_path('vendor/bin/phpunit');
        }

        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($phpunit);
        if ($filter = $this->option('filter')) {
            $cmd .= ' --filter ' . escapeshellarg($filter);
        }

        passthru($cmd, $exitCode);
        return $exitCode;
    }
}
