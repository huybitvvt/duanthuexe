<?php

namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Repositories\SellOrderRepository;
use App\Entities\SellOrder;
use App\Validators\SellOrderValidator;

/**
 * Class SellOrderRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class SellOrderRepositoryEloquent extends BaseRepository implements SellOrderRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return SellOrder::class;
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
        $vehicle = data_get($params, 'vehicle', '');
        $customer = data_get($params, 'customer', '');
        $status = data_get($params, 'status', '');
        $store_id = data_get($params, 'store_id', '');
        $created_at = data_get($params, 'created_at', []);

        if ($vehicle) {
            $this->whereHas('orderItems', function ($query) use ($vehicle) {
                $query->whereHas('vehicle', function ($q) use ($vehicle) {
                    $q->where('name', 'LIKE', '%' . $vehicle . '%')
                        ->orWhere('license', 'LIKE', '%' . $vehicle . '%');
                });
            });
        }

        if ($store_id) {
            $this->where('store_id', $store_id);
        }

        if ($customer) {
            $this->whereHas('customer', function ($query) use ($customer) {
                $query->where('name', 'LIKE', '%' . $customer . '%')
                    ->orWhere('phone', 'LIKE', '%' . $customer . '%')
                    ->orWhere('email', 'LIKE', '%' . $customer . '%')
                    ->orWhere('id_card', 'LIKE', '%' . $customer . '%');
            });
        }

        if ($status) {
            $this->where('status', $status);
        }

        if (!empty($created_at)) {
            if ($created_at[0] != 'null') {
                $this->whereBetween('created_at', $created_at);
            }
        }
        return $this;
    }
}
