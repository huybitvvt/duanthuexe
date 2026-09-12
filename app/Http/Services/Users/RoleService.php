<?php

namespace App\Http\Services\Users;

use App\Repositories\RoleRepository;

class RoleService
{
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * @return mixed
     */
    public function all()
    {
        return $this->roleRepository->all();
    }
}
