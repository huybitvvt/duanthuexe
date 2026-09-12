<?php

namespace App\Repositories;

use App\Models\Bank; 
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface BankRepository.
 *
 * @package namespace App\Repositories;
 */
interface BankRepository extends RepositoryInterface
{
    public function index(Request $request);
    public function all($columns = ['*']);

    public function store(Request $request);

    public function edit(Request $request, Bank $bank);

   
}
