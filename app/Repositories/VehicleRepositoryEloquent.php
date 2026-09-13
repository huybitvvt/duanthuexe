<?php

namespace App\Repositories;

use App\Models\Vehicle;
use App\Validators\Vehicle\VehicleValidator;
use Illuminate\Support\Facades\Auth;
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
        $keyword = data_get($params, 'name', data_get($params, 'keyword', ''));
        $status = data_get($params, 'status', '');
        $store_id = data_get($params, 'store_id', '');
        $created_at = data_get($params, 'created_at', []);
        $type = data_get($params, 'type', '');
        $type_of_service_id = data_get($params, 'type_of_service_id', '');
        $maintenance_status = data_get($params, 'maintenance_status', '');
        $user = Auth::user();

        if ($keyword) {
            $this->where(function ($query) use ($keyword) {
                $query->where('name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('license', 'LIKE', '%' . $keyword . '%');
            });
        }
        if ($type_of_service_id != '') {
            $this->where('type_of_service_id', $type_of_service_id);
        }
        if ($status) {
            $this->where('status', $status);
        }

        if ($store_id) {
            $this->where('store_id', intval($store_id));
        }

        if ($store_id) {
            $this->where('store_id', $store_id);
        }

        if ($type) {
            $this->where('type', $type);
        }

        if (!empty($created_at)) {
            if ($created_at[0] != 'null') {
                $this->whereBetween('created_at', $created_at);
            }
        }
        if ($maintenance_status) {
           
            switch ($maintenance_status) {
                case 1:
               
                    $this->whereHas('maintenanceVehicle', function ($query) {
                        $query->where('days_until_due', '<=', 0);
                    });
                    break;
                case 2:
                    
                    $this->whereHas('maintenanceVehicle', function ($query) {
                        $query->where('days_until_due', '<=', 3);
                    });
                    break;
                case 3:
                 
                    $this->whereHas('maintenanceVehicle', function ($query) {
                        $query->where('days_until_due', '<=', 7);
                    });
                    break;
                default:
                   
                    break;
            }
        }

      
        return $this;

    }
}
