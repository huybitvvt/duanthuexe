<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Http\Controllers\Controller;
use App\Http\Services\BankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    //
    protected $bankService;

    public function __construct(BankService $bankService)
    {
        $this->bankService = $bankService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */

     public function all(Request $request):  JsonResponse
     {
        $all = $this->bankService->all();
       return $this->successResponse($all);
     }
 
    public function index(Request $request): JsonResponse
    {  
        $banks = $this->bankService->index($request);
        return $this->successResponse($banks);
      
    }

    /**
     * @param Bank $bank
     * @return JsonResponse
     */
    public function show(Bank $bank): JsonResponse
    {
        
        $bank->load(['transactions']);
        $sum = $bank->transactions->sum(function ($transaction) {
            return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        }) ;
        $sum +=  $bank->opening_balance;
        // Update the current_balance property of the bank object
        $bank->current_balance = $sum;
    
        return $this->successResponse($bank);
        
        
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // public function searchbankByIdCard(Request $request): JsonResponse
    // {
    //     $bank = $this->bankService->searchbankByIdCard($request);
    //     return $this->successResponse($bank);
    // }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $bank = $this->bankService->store($request);
        return $this->successResponse($bank);
    }

    /**
     * @param Request $request
     * @param Bank $bank
     * @return JsonResponse
     */
    public function update(Request $request, Bank $bank): JsonResponse
    {
        $this->bankService->update($request, $bank);
        return $this->successResponse();
    }

    /**
     * @param Bank $bank
     * @return JsonResponse
     */
    public function destroy(Bank $bank): JsonResponse
    {
        // $bank->delete();
		Bank::where('id', $bank->id)->update(['status' => 'Inactive']);
        return $this->successResponse();
    }
}


 



