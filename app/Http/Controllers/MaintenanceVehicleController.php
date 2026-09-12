<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceVehicle;

use App\Models\Vehicle;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceVehicleController extends Controller
{
   public function index(Request $request){
   
    $items = MaintenanceVehicle::query();

    $paginator = $items->orderBy('id', 'DESC')->paginate(config('app.paginate', 20));
    
    return  $paginator;
   }

   public function putOrPost(Request $request){
 
        $data = $request->all();
        $this->putOrPostArr($data);
  
   }

public function putOrPostArr($data){
    foreach ($data as $key=>$attributes){
        if (isset($attributes['id'])){
            $item = MaintenanceVehicle::find($attributes['id']);
            $item->update($attributes);
        } else {
            MaintenanceVehicle::create($attributes);
        }
     }
     return $this->successResponse('','Maintenance vehicle created/updated successfully.');
}


   public function destroy(  MaintenanceVehicle $maintenanceVehicle){

         $maintenanceVehicle->delete();
    
        return $this->successResponse();
   }
 
  
}


 



