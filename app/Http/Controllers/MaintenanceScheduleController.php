<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;

class MaintenanceScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $is_all = $request->get('is_all');
        $params = $request->all();
        $items = MaintenanceSchedule::query()->with(['maintenanceType','vehicle']);

 
        if (isset($params['keyword'])){
            $items->whereHas('vehicle', function($query ) use ($params){
                $query->where('name','LIKE', '%' . $params['keyword'] . '%')
                ->orWhere('license', 'LIKE', '%' . $params['keyword'] . '%');
            });
            
            
        }
      
    
        if (isset($params['start_date'])){
            $items->where('next_time_manual','>=',  $params['start_date']);
        }
        if (isset($params['end_date'])){
            $items->where('next_time_manual','<=',  $params['end_date']);
        }

        $items->orderBy('id', 'DESC');


        if ($is_all){
             
            return $this->successResponse($items->get(),'Successfully get all');
        } else{
            $paginator = $items->paginate(config('app.paginate', 20));
            
            return $this->successResponse($paginator,'Successfully get maintenance schedules.');
        }
       
        
        
    }

    
    public function putOrPost(Request $request){
 
        $data = $request->all();
       
        $res = $this->putOrPostArr([$data]);
        return $res;
    }

    public function putOrPostArr($data){
        
        foreach ($data as $key=>$attributes){
          
            if (isset($attributes['id'])){
                $item = MaintenanceSchedule::find($attributes['id']) ;
                $item->update($attributes);
            } else {
                $item = MaintenanceSchedule::where('vehicle_id', $attributes['vehicle_id'])
                ->where('maintenance_type_id', $attributes['maintenance_type_id'])
                ->first();
                
                if ($item) {
                    $item->update($attributes);
                } else {
                    MaintenanceSchedule::create($attributes);
                }
                
            }
                
            }
      
        return $this->successResponse('','Maintenance schedule created/updated successfully.');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
      
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $params = $request->all();   
        MaintenanceSchedule::create( $params);
        return $this->successResponse('','Maintenance schedule created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\MaintenanceSchedule  $maintenanceSchedule
     * @return \Illuminate\Http\Response
     */
    public function show(MaintenanceSchedule $maintenanceSchedule)
    {
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\MaintenanceSchedule  $maintenanceSchedule
     * @return \Illuminate\Http\Response
     */
    public function edit(MaintenanceSchedule $maintenanceSchedule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\MaintenanceSchedule  $maintenanceSchedule
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MaintenanceSchedule $maintenanceSchedule)
    {
         
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\MaintenanceSchedule  $maintenanceSchedule
     * @return \Illuminate\Http\Response
     */
    public function destroy(MaintenanceSchedule $maintenanceSchedule)
    {
        $maintenanceSchedule->delete();
        return $this->successResponse('','Maintenance schedule deleted successfully.');
    }
}
