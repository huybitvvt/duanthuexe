<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRule;

use App\Models\Vehicle;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaintenanceRuleController extends Controller
{
   public function index(Request $request){
   
    $items = MaintenanceRule::query();

    $paginator = $items->orderBy('id', 'DESC')->paginate(config('app.paginate', 20));
    
    return  $paginator;
   }

   public function putOrPost(Request $request){
 
        $validated = Validator::make(['rules' => $request->all()], [
            'rules' => 'required|array|min:1',
            'rules.*' => 'required|array',
            'rules.*.id' => 'nullable|integer|exists:maintenance_rules,id',
            'rules.*.maintenance_type_id' => 'required|integer|exists:maintenance_types,id',
            'rules.*.value' => 'required|integer|min:1',
            'rules.*.next_time' => 'nullable|date',
        ])->validate();
        return DB::transaction(function () use ($validated) {
            return $this->putOrPostArr($validated['rules']);
        });
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


 



