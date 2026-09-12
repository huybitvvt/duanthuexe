<?php

namespace App\Http\Controllers;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use App\Models\MaintenanceVehicle;
use App\Models\MaintenanceSchedule;
use App\Http\Controllers\Controller;
use App\Http\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceLogController extends Controller
{
    protected $vehicleService;
    public function __construct(VehicleService $vehicleService){
        $this->vehicleService = $vehicleService;
    }


   public function index(Request $request){
    $params = $request->all();
    $items = MaintenanceLog::with(['maintenanceType'])->join('vehicles','vehicles.id','=','maintenance_log.vehicle_id')
    
    
    ->select('maintenance_log.*', 'vehicles.name', 'vehicles.license');

    if (isset($params['name'])){
        $items->where('name','LIKE', '%' . $params['name'] . '%')
        ->orWhere('license', 'LIKE', '%' . $params['name'] . '%');
    }
    if (isset($params['start_date'])){
        $items->where('maintenance_log.created_at','>=',  $params['start_date']);
        
    }
    if (isset($params['end_date'])){
        $items->where('maintenance_log.created_at','<=',  $params['end_date']);
        
    }
  
 


    $items->orderBy('maintenance_log.id', 'DESC');

    $paginator = $items->paginate(config('app.paginate', 20));
    
    return  $paginator;
   }

   public function putOrPost(Request $request){
        $params = $request->all();
        
        MaintenanceLog::create( $params);
        // $interval = $this->vehicleService->getMaintenanceInterval($params['vehicle_id'],$params['maintenance_type_id']);
        
        // $nextTimeAuto = date('Y-m-d H:i:s', strtotime($params['maintenance_at']) + $interval * 86400);

        // MaintenanceSchedule::where('vehicle_id','=',$params['vehicle_id'])->update(['next_time_auto'=>$nextTimeAuto]);
        return $this->successResponse('','Maintenance log created/updated successfully.');
   }
   public function destroy(  MaintenanceLog $maintenanceLog){
 
        //   $vehicle_id = $maintenanceLog->vehicle_id;
        //   $maintenance_type_id = $maintenanceLog->maintenance_type_id;

        //   $interval = $this->vehicleService->getMaintenanceInterval($vehicle_id,$maintenance_type_id);
         
        //  $closestMaintenanceDate = MaintenanceLog::where('vehicle_id','=',$vehicle_id)->orderBy('maintenance_at', 'desc')->first();
      
        //  if ($closestMaintenanceDate) {
        //     MaintenanceVehicle::where('vehicle_id','=',$vehicle_id)->update(['next_time_auto'=>$closestMaintenanceDate->maintenance_at]);    
        // }  
         
        $maintenanceLog->delete();
        return $this->successResponse();
   }
 
  
}


 



