<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $items = isset($data['name']) ? [$data] : $data;
        if (!is_array($items)) {
            return $this->errorResponse('Dữ liệu hình thức bảo dưỡng không hợp lệ.', 422);
        }
        foreach ($items as $attributes) {
            if (!is_array($attributes) || trim((string) ($attributes['name'] ?? '')) === '' || trim((string) ($attributes['note'] ?? '')) === '') {
                return $this->errorResponse('Vui lòng nhập tên và ghi chú hình thức bảo dưỡng.', 422);
            }
        }
        return DB::transaction(function () use ($items) {
            foreach ($items as $attributes) {
                if (!is_array($attributes)) continue;
                $name = trim((string) ($attributes['name'] ?? ''));
                $note = trim((string) ($attributes['note'] ?? ''));
                $values = ['name' => $name, 'note' => $note];
                $item = !empty($attributes['id']) ? MaintenanceType::find($attributes['id']) : null;
                if ($item) $item->update($values);
                else MaintenanceType::create($values);
            }
            return $this->successResponse('', 'Cập nhật hình thức bảo dưỡng thành công.');
        });
    }

    public function putOrPostArr($data){
        foreach ($data as $key=>$attributes){
            if (isset($attributes['id'])){
                $item = MaintenanceType::find($attributes['id']);
                if ($item) $item->update($attributes);
                else MaintenanceType::create($attributes);
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
