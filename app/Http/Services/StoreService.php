<?php

namespace App\Http\Services;

use App\Interfaces\ICrud;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Support\HimotoStores;

class StoreService implements ICrud
{
    private $storeRepository;

    public function __construct(StoreRepository $storeRepository)
    {
        $this->storeRepository = $storeRepository;
    }

    public function all()
    {
		return HimotoStores::query()->where('status', 'opening')->orderBy('id')->get();
    }

    public function index(array $params)
    {
		$query = HimotoStores::query()->where('status', 'opening')->orderBy('id');
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
