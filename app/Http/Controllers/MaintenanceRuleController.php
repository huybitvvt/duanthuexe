<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRule;

use App\Models\Vehicle;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRuleController extends Controller
{
   public function index(Request $request){
   
    $items = MaintenanceRule::query();

    $paginator = $items->orderBy('id', 'DESC')->paginate(config('app.paginate', 20));
    
    return  $paginator;
   }

   public function putOrPost(Request $request){
 
        $data = $request->all();
        $res = $this->putOrPostArr($data);
        return $res;
   }

public function putOrPostArr($data){
    foreach ($data as $key=>$attributes){
        if (isset($attributes['id'])){
            $item = MaintenanceRule::find($attributes['id']);
            $item->update($attributes);
        } else {
            // dd($attributes);
            MaintenanceRule::create($attributes);
        }
     }
     return $this->successResponse('','Maintenance rule created/updated successfully.');
}


   public function destroy(  MaintenanceRule $maintenanceRule){

         $maintenanceRule->delete();
    
        return $this->successResponse();
   }
 
  
}


 



