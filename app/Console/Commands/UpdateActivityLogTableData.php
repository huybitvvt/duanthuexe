<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class UpdateActivityLogTableData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_table:activity_logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update data of activity_logs table';

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
        $activities = ActivityLog::query();
        $total = (clone($activities))->get()->count();
        $this->output->progressStart($total / 500);
        $activities->chunkById(500, function ($activityLogs) {
            foreach ($activityLogs as $activityLog) {
                $activityLog->update([
                    'order_id' => (int)filter_var($activityLog->name, FILTER_SANITIZE_NUMBER_INT),
                ]);
            }
            $this->output->progressAdvance();
        }, 'id');
        dump('done activity_logs table');
    }
}
