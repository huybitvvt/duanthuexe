<?php

namespace App\Repositories;

use App\Models\Store;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class StoreRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class StoreRepositoryEloquent extends BaseRepository implements StoreRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Store::class;
    }



    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
    }

	public function all($columns = ['*'])  
    {
    
        $stores = $this->getModel()->newQuery();
        return $stores->where('status', 'opening')->get();
    }

}
