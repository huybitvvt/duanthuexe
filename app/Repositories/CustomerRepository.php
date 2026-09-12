<?php

namespace App\Repositories;

use App\Entities\Customer;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CustomerRepository.
 *
 * @package namespace App\Repositories;
 */
interface CustomerRepository extends RepositoryInterface
{
    public function index(Request $request);

    public function store(Request $request);

    public function edit(Request $request, Customer $customer);

    public function searchCustomerByIdCard(Request $request);
}
