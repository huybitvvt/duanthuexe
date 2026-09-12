<?php


namespace App\Repositories;


use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;

interface OrderRepository extends RepositoryInterface
{
    public function index(Request $request);

    public function store(array $params);
}
