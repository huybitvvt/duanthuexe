<?php

namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Repositories\SellOrderItemRepository;
use App\Entities\SellOrderItem;
use App\Validators\SellOrderItemValidator;

/**
 * Class SellOrderItemRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class SellOrderItemRepositoryEloquent extends BaseRepository implements SellOrderItemRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return SellOrderItem::class;
    }

    

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    
}
