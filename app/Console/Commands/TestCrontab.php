<?php

namespace App\Console\Commands;
 
use Illuminate\Console\Command;
use App\Helpers\CarRentalHelper;
use App\Helpers\DateTimeHelper;

class TestCrontab extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:crontab';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = DateTimeHelper::now();
        $string = $now->toString();

        info('Test Crontab at ' . $string);
        
    }
}
