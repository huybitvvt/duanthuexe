<?php

namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Repositories\AddOnOrderRepository;
use App\Entities\AddOnOrder;
use App\Validators\AddOnOrderValidator;

/**
 * Class AddOnOrderRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class AddOnOrderRepositoryEloquent extends BaseRepository implements AddOnOrderRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return AddOnOrder::class;
    }

    

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    
}
