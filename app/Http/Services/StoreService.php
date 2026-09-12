<?php

namespace App\Http\Services;

use App\Interfaces\ICrud;
use App\Models\Store;
use App\Repositories\StoreRepository;

class StoreService implements ICrud
{
    private $storeRepository;

    public function __construct(StoreRepository $storeRepository)
    {
        $this->storeRepository = $storeRepository;
    }

    public function all()
    {
        // return Store::all();
		$query = $this->storeRepository;
		$query = $query->where('status', 'opening');
		return $query->get();
    }

    public function index(array $params)
    {
		$query = $this->storeRepository;
		$query = $query->where('status', 'opening');
        return $query->paginate(20);
    }

    public function store(array $params)
    {
      return $this->storeRepository->create($params);
    }

    public function update($id, array $params)
    {
       return $this->storeRepository->update($params, $id);
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }


}
