<?php

namespace App\Http\Controllers\Customer;

use App\Entities\Customer;
use App\Http\Controllers\Controller;
use App\Http\Services\CustomerService;
use App\Validators\CustomerValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exports\CustomersExport;
use Maatwebsite\Excel\Facades\Excel;  
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerService->index($request);
        return $this->successResponse($customers);
    }

    /**
     * @param Customer $customer
     * @return JsonResponse
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['orders' => function ($query) {
            $query->orderBy('id', 'DESC');
        }]);

        return $this->successResponse($customer);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function searchCustomerByIdCard(Request $request): JsonResponse
    {
        $customer = $this->customerService->searchCustomerByIdCard($request);
        return $this->successResponse($customer);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->replace($request->validate(CustomerValidator::rules()));
        $customer = $this->customerService->store($request);
        return $this->successResponse($customer);
    }

    /**
     * @param Request $request
     * @param Customer $customer
     * @return JsonResponse
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->replace($request->validate(CustomerValidator::rules(true)));
        $this->customerService->update($request, $customer);
        return $this->successResponse();
    }

    /**
     * @param Customer $customer
     * @return JsonResponse
     */
    public function destroy(Customer $customer): JsonResponse
    {
        DB::transaction(function () use ($customer) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            foreach (['orders', 'sell_orders', 'lease_contracts'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('customer_id', $customer->id)->exists()) {
                    throw ValidationException::withMessages(['customer' => 'Khách hàng đã có hợp đồng. Không thể xóa dữ liệu lịch sử.']);
                }
            }
            $customer->delete();
        });
        return $this->successResponse();
    }


    
    
}
