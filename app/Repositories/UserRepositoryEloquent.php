<?php

namespace App\Repositories;

use App\Models\User;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Validators\UserValidator;

/**
 * Class UserRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class UserRepositoryEloquent extends BaseRepository implements UserRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return User::class;
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
        $keyword = data_get($params, 'keyword', '');
        $status = data_get($params, 'status', '');
        $store_id = data_get($params, 'store_id', '');
        $role_id = data_get($params, 'role_id', '');
        $start_date = data_get($params, 'start_date', '');
        $end_date = data_get($params, 'end_date', '');

        if ($keyword) {
            $this->where(function ($query) use ($keyword) {
                $query->where('name', 'LIKE', '%' . $keyword . '%')
                ->orWhere('email', 'LIKE', '%' . $keyword .'%')
                ->orWhere('phone', 'LIKE', '%' . $keyword . '%');
            });
        }

        if ($status) {
            $this->where('status', $status);
        }

        if ($store_id) {
            $this->where('store_id', intval($store_id));
        }

        if ($role_id) {
            $this->where('role_id', intval($role_id));
        }

        if ($start_date) {
            $this->whereDate('created_at', '>=', $start_date);
        }

        if ($end_date) {
            $this->whereDate('created_at', '<=', $end_date);
        }

        return $this;
    }

}
