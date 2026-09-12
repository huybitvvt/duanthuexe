<?php

namespace App\Http\Services\Users;

use App\Interfaces\ICrud;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;


class UserService implements ICrud
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function store(array $data)
    {
        $data['password'] = $this->hashPassword($data['password']);
        return $this->userRepository->create($data);
    }

    public function index(array $params)
    {
        $limit = data_get($params, 'limit', config('app.paginate', 20));

        $items = $this->userRepository->with(['role_rel', 'store:id,store_name'])->filter($params)->orderBy('id', 'DESC');

		$user = auth()->user();
		if ($user->role_rel->slug !== 'quan-tri-vien') {
			$items->where('store_id', $user->store_id);

			if ($user->role_rel->slug !== 'quan-ly-cua-hang') {
				$items->where('id', $user->id);
			}
		}

        return $items->paginate($limit);
    }

    /**
     * @param $store_id
     * @return mixed
     */
    public function getByStore($store_id)
    {
        return $this->userRepository->with(['role_rel'])
            ->where('store_id', $store_id)
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'role_id']);
    }

    public function update($id, array $params)
    {   
        if (isset(  $params['password'])){
            $params['password'] = $this->hashPassword($params['password']);
        }
        return $this->userRepository->update($params, $id);
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }

    public function all()
    {
        // TODO: Implement all() method.
    }
    public function hashPassword($str){
        return Hash::make($str);
    }
}
