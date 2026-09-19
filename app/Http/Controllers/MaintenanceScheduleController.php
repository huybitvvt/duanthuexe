<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\DateTimeHelper;

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
            if (!empty($attributes['id'])){
                $item = MaintenanceSchedule::find($attributes['id']);
                if ($item) {
                    $item->update($attributes);
                } else {
                    MaintenanceSchedule::create($attributes);
                }
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
        return $this->successResponse('','Lưu lịch hẹn bảo dưỡng thành công.');
    }

    /**
     * Get upcoming or overdue maintenance schedules for dashboard & notifications.
     */
    public function upcoming(Request $request)
    {
        $now = DateTimeHelper::now();
        $daysAhead = (int) $request->input('days', 7);
        $limitDate = $now->copy()->addDays($daysAhead)->endOfDay();
        $storeId = $request->input('store_id');

        $query = MaintenanceSchedule::query()
            ->with(['maintenanceType:id,name', 'vehicle:id,name,license,store_id'])
            ->where(function ($q) use ($limitDate) {
                $q->whereNotNull('next_time_manual')
                  ->where('next_time_manual', '<=', $limitDate);
            })
            ->orWhere(function ($q) use ($limitDate) {
                $q->whereNotNull('next_time_auto')
                  ->where('next_time_auto', '<=', $limitDate);
            });

        if ($storeId && $storeId !== 'all') {
            $query->whereHas('vehicle', function ($vq) use ($storeId) {
                $vq->where('store_id', (int) $storeId);
            });
        }

        $items = $query->orderByRaw("COALESCE(next_time_manual, next_time_auto) ASC")
            ->take(50)
            ->get();

        $mapped = $items->map(function ($item) use ($now) {
            $dueDate = $item->next_time_manual ?: $item->next_time_auto;
            $carbonDue = Carbon::parse($dueDate)->timezone('Asia/Bangkok');

            if ($carbonDue->lt($now->copy()->startOfDay())) {
                $daysOver = (int) $now->diffInDays($carbonDue);
                $status = 'overdue';
                $statusText = $daysOver <= 1 ? 'Quá hạn 1 ngày' : "Quá hạn {$daysOver} ngày";
                $severity = 'danger';
            } elseif ($carbonDue->isToday()) {
                $status = 'today';
                $statusText = 'Đến hạn hôm nay';
                $severity = 'warning';
            } else {
                $daysLeft = (int) ceil($now->diffInHours($carbonDue) / 24);
                $status = 'upcoming';
                $statusText = "Còn {$daysLeft} ngày";
                $severity = 'info';
            }

            return [
                'id' => $item->id,
                'vehicle_id' => $item->vehicle_id,
                'vehicle_name' => $item->vehicle ? $item->vehicle->name : 'Xe #' . $item->vehicle_id,
                'vehicle_license' => $item->vehicle ? $item->vehicle->license : '',
                'store_id' => $item->vehicle ? $item->vehicle->store_id : null,
                'maintenance_type_id' => $item->maintenance_type_id,
                'maintenance_type_name' => $item->maintenanceType ? $item->maintenanceType->name : 'Bảo dưỡng',
                'next_time_manual' => $item->next_time_manual,
                'next_time_auto' => $item->next_time_auto,
                'due_date' => $carbonDue->format('d/m/Y H:i'),
                'due_date_iso' => $carbonDue->toIso8601String(),
                'status' => $status,
                'status_text' => $statusText,
                'severity' => $severity,
            ];
        });

        return $this->successResponse($mapped, 'Lấy danh sách xe cần bảo dưỡng thành công.');
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
        MaintenanceSchedule::create($params);
        return $this->successResponse('','Tạo lịch hẹn bảo dưỡng thành công.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\MaintenanceSchedule  $maintenanceSchedule
     * @return \Illuminate\Http\Response
     */
    public function show(MaintenanceSchedule $maintenanceSchedule)
    {
        $maintenanceSchedule->load(['maintenanceType', 'vehicle.store']);
        return $this->successResponse($maintenanceSchedule, 'Lấy chi tiết lịch hẹn bảo dưỡng thành công.');
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
