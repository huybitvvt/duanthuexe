<?php

namespace App\Repositories;

use App\Models\Vehicle;
use App\Validators\Vehicle\VehicleValidator;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;


/**
 * Class VehicleRepositoryEloquent.
 *
 * @package namespace App\Repositories\Vehicle;
 */
class VehicleRepositoryEloquent extends BaseRepository implements VehicleRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Vehicle::class;
    }


    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function filter(array $params)
    {
        return $this->applyFilters($this, $params);
    }

    /**
     * Apply the shared vehicle filters to either this repository or a plain
     * Eloquent query. Keeping this in one place prevents report queries from
     * loading every model just to reuse the same filters in PHP.
     */
    public function applyFilters($query, array $params)
    {
        $keyword = data_get($params, 'name', data_get($params, 'keyword', ''));
        $status = data_get($params, 'status', '');
        $store_id = data_get($params, 'store_id', '');
        $created_at = data_get($params, 'created_at', []);
        $type = data_get($params, 'type', '');
        $type_of_service_id = data_get($params, 'type_of_service_id', '');
        $maintenance_status = data_get($params, 'maintenance_status', '');
        if ($keyword) {
            $query->where(function ($vehicleQuery) use ($keyword) {
                $vehicleQuery->where('vehicles.name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('vehicles.license', 'LIKE', '%' . $keyword . '%');
            });
        }
        if ($type_of_service_id != '') {
            $query->where('vehicles.type_of_service_id', $type_of_service_id);
        }
        if ($status) {
            $query->where('vehicles.status', $status);
        }

        if ($store_id) {
            $query->where('vehicles.store_id', intval($store_id));
        }

        if ($type) {
            $query->where('vehicles.type', $type);
        }

        if (!empty($created_at)) {
            if ($created_at[0] != 'null') {
                $query->whereBetween('vehicles.created_at', $created_at);
            }
        }
        if ($maintenance_status) {
           
            switch ($maintenance_status) {
                case 1:
               
                    $query->whereHas('maintenanceVehicle', function ($maintenanceQuery) {
                        $maintenanceQuery->where('days_until_due', '<=', 0);
                    });
                    break;
                case 2:
                    
                    $query->whereHas('maintenanceVehicle', function ($maintenanceQuery) {
                        $maintenanceQuery->where('days_until_due', '<=', 3);
                    });
                    break;
                case 3:

                    $query->whereHas('maintenanceVehicle', function ($maintenanceQuery) {
                        $maintenanceQuery->where('days_until_due', '<=', 7);
                    });
                    break;
                default:
                   
                    break;
            }
        }

      
        return $query;

    }
}
