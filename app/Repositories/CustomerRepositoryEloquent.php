<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Entities\Customer;

/**
 * Class CustomerRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class CustomerRepositoryEloquent extends BaseRepository implements CustomerRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Customer::class;
    }


    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $customers = $this->getModel()->newQuery();

         // search

         $keyword = $request->get('keyword', '');

         if ($keyword) {
             $customers->where(function ($query) use ($keyword) {
                 $query->where('name', 'LIKE', '%' . $keyword . '%')
                     ->orWhere('phone', $keyword)
                     ->orWhere('id_card', $keyword);
             });
         }

         $phone = $request->get('phone', '');
         if ($phone) {
            $customers->where('phone', $phone);
         }

         $id_card = $request->get('id_card', '');
         if ($id_card) {
            $customers->where('id_card', $id_card);
         }

         $status = $request->get('status', '');
         if ($status) {
             $customers->where('status', $status);
         }
         
         // end search

        return $customers->orderBy('id', 'DESC')->paginate(config('app.paginate', 20));
    }

    public function store(Request $request)
    {
        $data = $request->all();
        return $this->getModel()->newQuery()->create($data);

    }

    public function edit(Request $request, Customer $customer)
    {
        $data = $request->all();
        $customer->update($data);
    }

    public function searchCustomerByIdCard(Request $request)
    {
        $query = $this->getModel()->newQuery();

        $idCard = $request->get('id_card');

        return $query->where('id_card', $idCard)->first();
    }

}
