<?php


namespace App\Http\Services;


use App\Entities\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Http\Request;

class CustomerService
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function index(Request $request)
    {
        return $this->customerRepository->index($request);
    }

    public function store(Request $request)
    {
        return $this->customerRepository->store($request);
    }

    public function update(Request $request, Customer $customer)
    {
        return $this->customerRepository->edit($request, $customer);
    }

    public function checkExistCustomer($id_card)
    {
        return $this->customerRepository->where('id_card', $id_card)
            ->first();
    }

    public function searchCustomerByIdCard(Request $request)
    {
        return $this->customerRepository->searchCustomerByIdCard($request);
    }
}
