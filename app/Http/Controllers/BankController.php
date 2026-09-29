<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Services\BankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\PermissionAccess;

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
        $this->authorizeBankList($request);
        $all = $this->bankService->all();
       return $this->successResponse($all);
     }
 
    public function index(Request $request): JsonResponse
    {  
        $this->authorizeBankList($request);
        $banks = $this->bankService->index($request);
        return $this->successResponse($banks);
      
    }

    /**
     * @param Bank $bank
     * @return JsonResponse
     */
    public function show(Request $request, Bank $bank): JsonResponse
    {
        $this->authorizeBank($request, (int) $bank->store_id);
        if (PermissionAccess::getRoleSlug($request->user()) === 'thue-so-huu-thu-hoi-no') {
            $request->validate(['date' => 'nullable|date_format:Y-m-d']);
            $date = $request->input('date', date('Y-m-d'));
            $prior = Transaction::where('bank_id', $bank->id)
                ->whereDate('created_at', '<', $date)
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN -value ELSE value END), 0) AS balance', [Transaction::CHI])
                ->value('balance');
            $movements = Transaction::where('bank_id', $bank->id)
                ->whereDate('created_at', $date)->orderBy('id')->limit(500)->get();
            $opening = (float) $bank->opening_balance + (float) $prior;
            $bank->setRelation('transactions', $movements);
            $bank->daily_date = $date;
            $bank->daily_opening_balance = $opening;
            $bank->daily_change = (float) Transaction::where('bank_id', $bank->id)
                ->whereDate('created_at', $date)
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN -value ELSE value END), 0) AS balance', [Transaction::CHI])
                ->value('balance');
            $bank->daily_movements_limited = $movements->count() === 500;
            $bank->current_balance = $opening + $bank->daily_change;
            return $this->successResponse($bank);
        }
        $bank->load(['transactions']);
        $sum = $bank->transactions->sum(function ($transaction) {
            return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        }) ;
        $sum +=  $bank->opening_balance;
        // Update the current_balance property of the bank object
        $bank->current_balance = $sum;
    
        return $this->successResponse($bank);
        
        
    }

    private function authorizeBankList(Request $request): void
    {
        $user = $request->user();
        if (PermissionAccess::allows($user, 'finance.bank.view_all')) {
            return;
        }
        PermissionAccess::can($user, 'finance.bank.view_store', (int) $user->store_id);
        if (!$user->store_id || ($request->filled('store_id')
            && (int) $request->input('store_id') !== (int) $user->store_id)) {
            abort(403, 'Bạn chỉ được xem tài khoản ngân hàng của cơ sở được phân công.');
        }
    }

    private function authorizeBank(Request $request, int $storeId): void
    {
        if (!PermissionAccess::allows($request->user(), 'finance.bank.view_all')) {
            PermissionAccess::can($request->user(), 'finance.bank.view_store', $storeId);
        }
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


 



