<?php

namespace App\Http\Services;

use App\Helpers\DateTimeHelper;
use App\Interfaces\ICrud;
use App\Models\Vehicle;
use App\Models\MaintenanceVehicle;
use App\Repositories\VehicleRepository;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\MaintenanceVehicleController;
use App\Http\Controllers\FileController;

class VehicleService implements ICrud
{
    private $vehicleRepository;

    public function __construct(VehicleRepository $vehicleRepository, MaintenanceVehicle $maintenanceVehicle, MaintenanceVehicleController $maintenanceVehicleController)
    {
        $this->maintenanceVehicleController = $maintenanceVehicleController;
        $this->maintenanceVehicle = $maintenanceVehicle;
        $this->vehicleRepository = $vehicleRepository;
    }
    

    public function index(array $params, $all = false)
    {
        $limit = data_get($params, 'limit', config('app.paginate'));
        $compact = filter_var(data_get($params, 'compact', false), FILTER_VALIDATE_BOOLEAN);
        $relations = ['store:id,store_name'];
        if (!$compact) {
            $relations = array_merge($relations, [
                'maintenanceLog.maintenanceType',
                'maintenanceVehicle',
                'maintenanceSchedule.maintenanceType',
                'images',
            ]);
        }

        $items = $this->vehicleRepository->with($relations)
            ->filter($params)
            ->orderBy('id', 'DESC');
		
        if ( $all ) {
            $results = $items->get();
        } else {
			$results = $items->paginate($limit);
		}

        if (!$compact) {
			foreach ( $results as $item ) {
				foreach ( $item->images as $image ) {
					if (($image->provider !== 'cloudinary' || !$image->url) && $image->key) {
						$image->url = FileController::get_temp_url( $image->key );
					}
				}
			}
        }

        return $results;
    }
    public function indexWithRevenue(array $params, $all = false)
    {
        $limit = data_get($params, 'limit', config('app.paginate'));
        $revenueTotals = $this->revenueTotalsQuery($params);

        $query = Vehicle::query()
            ->select('vehicles.*')
            ->selectRaw('COALESCE(vehicle_revenue.count_order, 0) as count_order')
            ->selectRaw('COALESCE(vehicle_revenue.revenue, 0) as revenue')
            ->leftJoinSub($revenueTotals, 'vehicle_revenue', function ($join) {
                $join->on('vehicle_revenue.vehicle_id', '=', 'vehicles.id');
            });

        if (filter_var(data_get($params, 'include_store', true), FILTER_VALIDATE_BOOLEAN)) {
            $query->with('store:id,store_name');
        }

        $this->vehicleRepository->applyFilters($query, $params);

        $sortBy = in_array(data_get($params, 'sort_by'), ['count_order', 'revenue'], true)
            ? data_get($params, 'sort_by')
            : 'revenue';
        $sortDirection = strtolower(data_get($params, 'sort_type', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection)->orderBy('vehicles.id', 'desc');

        return $all ? $query->get() : $query->paginate($limit);
    }

    /**
     * Aggregate rental count and revenue in SQL before pagination. The old
     * implementation hydrated every matching rental row, calculated totals in
     * PHP, sorted the whole collection and only then kept the current 20 rows.
     */
    private function revenueTotalsQuery(array $params)
    {
        $query = DB::table('order_vehicle_details')
            ->select('vehicle_id')
            ->selectRaw('COUNT(*) as count_order')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN COALESCE(handler_price, 0) <> 0 THEN handler_price ELSE COALESCE(total_money, 0) + COALESCE(money_out_date, 0) END), 0) as revenue'
            )
            ->whereNull('deleted_at');

        $startDate = data_get($params, 'start_date');
        if (!empty($startDate)) {
            $query->where('rent_at', '>=', DateTimeHelper::parse($startDate)->startOfDay());
        }

        $endDate = data_get($params, 'end_date');
        if (!empty($endDate)) {
            $query->where('rent_at', '<=', DateTimeHelper::parse($endDate)->endOfDay());
        }

        return $query->groupBy('vehicle_id');
    }

    /**
     * Build the vehicle summary with one aggregate query instead of loading
     * every vehicle, maintenance record and image into memory.
     */
    public function reportByParams(array $params): array
    {
        $query = Vehicle::query();
        $this->vehicleRepository->applyFilters($query, $params);

        $metrics = $query->selectRaw(
            "COALESCE(SUM(CASE WHEN vehicles.status <> ? THEN 1 ELSE 0 END), 0) as total_vehicle,
             COALESCE(SUM(CASE WHEN vehicles.status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_ready,
             COALESCE(SUM(CASE WHEN vehicles.status = ? THEN 1 ELSE 0 END), 0) as total_vehicle_using,
             COALESCE(SUM(CASE WHEN vehicles.status <> ? AND vehicles.type = ? THEN 1 ELSE 0 END), 0) as total_vehicle_ga,
             COALESCE(SUM(CASE WHEN vehicles.status <> ? AND vehicles.type = ? THEN 1 ELSE 0 END), 0) as total_vehicle_so,
             COALESCE(SUM(CASE WHEN vehicles.status <> ? AND vehicles.type = ? THEN 1 ELSE 0 END), 0) as total_vehicle_con,
             COALESCE(SUM(CASE WHEN vehicles.status <> ? THEN CAST(COALESCE(NULLIF(vehicles.cost_price, ''), '0') AS DECIMAL(18, 2)) ELSE 0 END), 0) as total_price",
            [
                Vehicle::STATUS_SOLD,
                Vehicle::STATUS_READY,
                Vehicle::STATUS_USING,
                Vehicle::STATUS_SOLD,
                Vehicle::TYPE_XEGA,
                Vehicle::STATUS_SOLD,
                Vehicle::TYPE_XESO,
                Vehicle::STATUS_SOLD,
                Vehicle::TYPE_XECON,
                Vehicle::STATUS_SOLD,
            ]
        )->first();

        return [
            'total_vehicle' => (int) ($metrics->total_vehicle ?? 0),
            'total_vehicle_ready' => (int) ($metrics->total_vehicle_ready ?? 0),
            'total_vehicle_using' => (int) ($metrics->total_vehicle_using ?? 0),
            'total_vehicle_ga' => (int) ($metrics->total_vehicle_ga ?? 0),
            'total_vehicle_so' => (int) ($metrics->total_vehicle_so ?? 0),
            'total_vehicle_con' => (int) ($metrics->total_vehicle_con ?? 0),
            'total_price' => (float) ($metrics->total_price ?? 0),
        ];
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
