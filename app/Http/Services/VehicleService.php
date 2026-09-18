<?php

namespace App\Http\Services;

use App\Http\Services\OrderService;
use App\Interfaces\ICrud;
use App\Models\Vehicle;
use App\Models\MaintenanceVehicle;
use App\Models\MaintenanceType;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceSchedule;
use App\Repositories\VehicleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

use App\Http\Controllers\MaintenanceVehicleController;
use App\Http\Controllers\FileController;

class VehicleService implements ICrud
{
    private $vehicleRepository;

    public function __construct(  OrderService $orderService,VehicleRepository $vehicleRepository,MaintenanceVehicle $maintenanceVehicle, MaintenanceVehicleController $maintenanceVehicleController)
    {
        $this->maintenanceVehicleController = $maintenanceVehicleController;
        $this->maintenanceVehicle = $maintenanceVehicle;
        $this->orderService = $orderService;
        $this->vehicleRepository = $vehicleRepository;
    }
    

    public function index(array $params, $all = false)
    {
        $limit = data_get($params, 'limit', config('app.paginate'));
        $items = $this->vehicleRepository->with(['store:id,store_name','maintenanceLog.maintenanceType','maintenanceVehicle','maintenanceSchedule.maintenanceType'])
            ->filter($params)
            ->orderBy('id', 'DESC');

		$items->with('images');
		
        if ( $all ) {
            $results = $items->get();
        } else {
			$results = $items->paginate($limit);
		}

		foreach ( $results as $item ) {
			foreach ( $item->images as $image ) {
				if (($image->provider !== 'cloudinary' || !$image->url) && $image->key) {
					$image->url = FileController::get_temp_url( $image->key );
				}
			}
		}

        return $results;
    }
    public function indexWithRevenue(array $params, $all = false)
    {
        $limit = data_get($params, 'limit', config('app.paginate'));
        $startDate = data_get($params, 'start_date', '');
        $endDate = data_get($params, 'end_date', '');

        $items  = $this->vehicleRepository->with(['store:id,store_name','orderVehicleDetails' 
        => function($query) use ($startDate,$endDate){
         
            if ($startDate !== null) {
                $query->whereDate('order_vehicle_details.rent_at', '>=', $startDate);
            }
            if ($endDate !== null){
                $query->whereDate('order_vehicle_details.rent_at', '<=', $endDate);
            }
            $query->orderBy('id', 'desc');
        }
        ])
        ->filter($params)->get();
 
          
        $items->transform(function ($vehicle) {
            $vehicle->count_order = $vehicle->orderVehicleDetails->count();  
                $vehicle->revenue = $vehicle->orderVehicleDetails->sum(function ($item) {
                    return $this->orderService->getOrderItemPrice($item);        
              
 
                });
        
            return $vehicle;
            
        });
       
        if (isset($params['sort_type']) && strtolower($params['sort_type']) === 'asc') {
            $items = $items->sortBy(isset($params['sort_by']) ? $params['sort_by'] : 'revenue');
        } else {
            $items = $items->sortByDesc( isset($params['sort_by']) ? $params['sort_by'] : 'revenue' );
        }

      
        $items = $items->values() ;
        if ($all){
            return $items;
        } else {
            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 20;  
           
            $currentPageItems = $items->slice(($page - 1) * $perPage, $perPage);
            $paginator = new LengthAwarePaginator($currentPageItems, $items->count(), $perPage, $page);
            return $paginator;
        }  
     
            
     
    }

    public function report($items)
    {
        $total_vehicle = $items->where('status', '!=', Vehicle::STATUS_SOLD)->count();
        $total_vehicle_ready = $items->where('status', Vehicle::STATUS_READY)->count();
        $total_vehicle_using = $items->where('status', Vehicle::STATUS_USING)->count();
        $total_vehicle_ga = $items->where('status', '!=', Vehicle::STATUS_SOLD)->where('type', Vehicle::TYPE_XEGA)->count();
        $total_vehicle_so = $items->where('status', '!=', Vehicle::STATUS_SOLD)->where('type', Vehicle::TYPE_XESO)->count();
        $total_vehicle_con = $items->where('status', '!=', Vehicle::STATUS_SOLD)->where('type', Vehicle::TYPE_XECON)->count();
        $total_price = $items->where('status', '!=', Vehicle::STATUS_SOLD)->sum('cost_price');

        return [
            'total_vehicle' => $total_vehicle,
            'total_vehicle_ready' => $total_vehicle_ready,
            'total_vehicle_using' => $total_vehicle_using,
            'total_vehicle_ga' => $total_vehicle_ga,
            'total_vehicle_so' => $total_vehicle_so,
            'total_vehicle_con' => $total_vehicle_con,
            'total_price' => $total_price
        ];
    }

    public function all()
    {
        // TODO: Implement all() method.
    }

    public function store(array $params)
    {    
      
        return $this->vehicleRepository->create($params);
    }

    public function update($id, array $params)
    {
		$data_to_update = data_get($params, 'maintenance_settings', []);
		foreach ($data_to_update as &$item){
			$item['vehicle_id'] = $params['id'];
		}
		unset($item);

		$data_to_destroy = data_get($params, 'maintenance_settings_to_destroy', []);
		
		$this->updateMaintenanceSettings($data_to_update,$data_to_destroy);
	
		return $this->vehicleRepository->update($params, $id);
    }
    public function updateMaintenanceSettings($data_to_update = [],$data_to_destroy = []){
        
        $this->maintenanceVehicleController->putOrPostArr($data_to_update);
        foreach ($data_to_destroy as $item){
            $this->maintenanceVehicle->destroy($item);
        }
    }
  
    public function delete()
    {
        // TODO: Implement delete() method.
    }

    public function updateStatus(int $vehicle_id, string $status)
    {
        return $this->vehicleRepository->where('id', $vehicle_id)->update(['status' => $status]);
    }

    public function checkStatusOtherSold($id)
    {
        return $this->vehicleRepository->where('id', $id)->where('status', '!=', Vehicle::STATUS_SOLD)->first();
    }

    


}
