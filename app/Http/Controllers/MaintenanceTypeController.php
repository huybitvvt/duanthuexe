<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceType;
use Illuminate\Http\Request;

class MaintenanceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $is_all = $request->get('is_all');
        $items = MaintenanceType::query();
        $items->orderBy('id', 'DESC');
        if ($is_all){
             
            return $this->successResponse($items->get(),'Successfully get all');
        } else{
            $paginator = $items->paginate(config('app.paginate', 20));
            
            return $this->successResponse($paginator,'Successfully get maintenance types.');
        }
       
        
        
    }

    
    public function putOrPost(Request $request){
 
        $data = $request->all();
        $res = $this->putOrPostArr($data);
        return $res;
    }

    public function putOrPostArr($data){
        foreach ($data as $key=>$attributes){
            if (isset($attributes['id'])){
                $item = MaintenanceType::find($attributes['id']);
                $item->update($attributes);
            } else {
        
                MaintenanceType::create($attributes);
            }
        }
        return $this->successResponse('','Maintenance type created/updated successfully.');
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
        MaintenanceType::create( $params);
        return $this->successResponse('','Maintenance type created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\MaintenanceType  $maintenanceType
     * @return \Illuminate\Http\Response
     */
    public function show(MaintenanceType $maintenanceType)
    {
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\MaintenanceType  $maintenanceType
     * @return \Illuminate\Http\Response
     */
    public function edit(MaintenanceType $maintenanceType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\MaintenanceType  $maintenanceType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MaintenanceType $maintenanceType)
    {
         
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\MaintenanceType  $maintenanceType
     * @return \Illuminate\Http\Response
     */
    public function destroy(MaintenanceType $maintenanceType)
    {
        $maintenanceType->delete();
        return $this->successResponse('','Maintenance type deleted successfully.');
    }
}
