<?php

namespace App\Repositories;

use Illuminate\Http\Request;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Repositories\TransactionRepository;
use App\Models\Transaction;
use App\Models\Bank;
use App\Validators\TransactionValidator;

/**
 * Class TransactionRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class TransactionRepositoryEloquent extends BaseRepository implements TransactionRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Transaction::class;
    }



    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    public function store(Request $request)
    {
        $data = $request->all();
 
        return $this->getModel()->newQuery()->create($data);
    }


    public function create( $data)
    {
 
        return $this->getModel()->newQuery()->create($data);
    }

    public function edit(Request $request, Transaction $tran)
    {
        $data = $request->all();
      
        $tran->update($data);
    }
  
}
