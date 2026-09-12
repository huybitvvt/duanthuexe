<?php

namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Repositories\OrderVehicleDetailRepository;
use App\Models\OrderVehicleDetail;
use App\Validators\OrderVehicleDetailValidator;

/**
 * Class OrderVehicleDetailRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class OrderVehicleDetailRepositoryEloquent extends BaseRepository implements OrderVehicleDetailRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return OrderVehicleDetail::class;
    }



    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

}
