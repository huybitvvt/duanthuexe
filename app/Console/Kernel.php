<?php

namespace App\Console;

use App\Console\Commands\CaculateMinuteLateOrder;
use App\Console\Commands\MaintenanceScheduleCron; 
use App\Console\Commands\GetLead; 
use App\Console\Commands\ConverAddOnToTransaction;
 
use App\Console\Commands\DeleteOrderItemNotExistOrder;
use App\Console\Commands\SettingCommand;
use App\Console\Commands\SyncCustomerCommand;
use App\Console\Commands\TestCrontab;
use App\Console\Commands\UpdateTransactionTableData;
use App\Console\Commands\ScanCustomerReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
    
        SyncCustomerCommand::class,
        UpdateTransactionTableData::class,
        CaculateMinuteLateOrder::class,
        MaintenanceScheduleCron::class,
        GetLead::class,
        TestCrontab::class,
        SettingCommand::class,
        DeleteOrderItemNotExistOrder::class,
        ScanCustomerReminders::class,
        
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('command:crontab')->everyMinute();
         $schedule->command('feature:calculate-minute-late-orders')->everyMinute();
         $schedule->command('feature:maintenance-schedule')->everyMinute();
         $schedule->command('feature:get-lead')->cron('*/15 * * * *');
         $schedule->command('himoto:scan-reminders')->dailyAt('08:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
        //  $schedule->command('feature:get-lead')->everyMinute(); // For testing
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
