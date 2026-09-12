<?php


namespace App\Repositories;


use App\Models\PriceVehicle;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class PriceVehicleRepositoryEloquent extends BaseRepository implements PriceVehicleRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return PriceVehicle::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function index(Request $request)
    {
        $orders = $this->getModel()->newQuery();
        return $orders->get();
    }
}
