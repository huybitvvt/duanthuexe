<?php

namespace App\Console\Commands;


use App\Models\MaintenanceVehicle;
use Carbon\Carbon;
use DateTime;
use DateInterval;
use Illuminate\Console\Command;
use App\Models\Vehicle;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceType;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceLog;

class MaintenanceScheduleCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feature:maintenance-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Điền vào bảng lịch hẹn bảo dưỡng';

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
     */
    public function handle()
    {
        info('Điền vào bảng lịch hẹn bảo dưỡng');
        

        $vehicles = Vehicle::where('status', 'using')
                    ->orWhere('status', 'ready')
                    ->get();
        

        foreach ($vehicles as $vehicle){
            $this->handleVehicle($vehicle->id);
        }
    
        dump('Done: Điền vào bảng lịch hẹn bảo dưỡng');
    }
    
 
    public function handleVehicle($vehicle_id){
        $maintenance_types = MaintenanceType::all();
        foreach ($maintenance_types as $type){
            $schedule = MaintenanceSchedule::where('vehicle_id',$vehicle_id)->where('maintenance_type_id',$type->id)->first();
            if ($schedule && $schedule->next_time_manual){
                continue;
            } else {
                $schedule = MaintenanceSchedule::create(['vehicle_id'=>$vehicle_id, 'maintenance_type_id' => $type->id]);
                $interval_in_days  = $this->getMaintenanceInterval($vehicle_id,$type->id);
                $latest_maintenance_date = $this->getLastMaintenanceDate($vehicle_id,$type->id);
                if ( $latest_maintenance_date ){
                    $date = new DateTime($latest_maintenance_date);
                    $date->add(new DateInterval('P' . $interval_in_days . 'D'));
                    $next_day = $date->format('Y-m-d');  
                    $schedule->update([ 'next_time_auto'=>$next_day]);
                } else {
                    $currentDate = Carbon::now();
                    $datetimeFormat = $currentDate->format('Y-m-d H:i:s');
                    $schedule->update([ 'next_time_auto'=> $datetimeFormat ]);
                }
               
            }
        }
    }
    public function getLastMaintenanceDate($vehicle_id,$maintenance_type_id){
        $log = MaintenanceLog::where('vehicle_id',$vehicle_id)->where('maintenance_type_id',$maintenance_type_id)->orderBy('maintenance_at', 'desc')->first();
        if ($log){
            return $log->maintenance_at;
        } else {
            return null;
        }
        


    }
    public function getMaintenanceInterval($vehicle_id, $maintenance_type_id){
        
        $maintenanceVehicle = MaintenanceVehicle::where('vehicle_id', $vehicle_id)
        ->where('maintenance_type_id', $maintenance_type_id)
        ->first();

        if ($maintenanceVehicle) {
            $individual_interval = $maintenanceVehicle->individual_interval;
        } else {
            $individual_interval = null;
        }
        $maintenance_rule = MaintenanceRule::where('maintenance_type_id', $maintenance_type_id)->first();
        if ($maintenance_rule) {
            $general_interval = $maintenance_rule->value;
        } else {
            $general_interval = null;
        }
       
        return   $individual_interval  ??   $general_interval;
    }
    
}
