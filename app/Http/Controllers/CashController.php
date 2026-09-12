<?php

namespace App\Http\Controllers;

use App\Models\Cash;
use App\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Bank; 
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

 


class CashController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function getAllCash(){
        $query = Cash::join('stores', 'cash.store_id', '=', 'stores.id')->where('cash.status', 'Active')->select('cash.*', 'stores.store_name');
        $user = auth()->user();
        if ($user->role_rel->slug !== 'quan-tri-vien') {
            $query->where('cash.store_id', $user->store_id);
        }     
        return $query;
    }
    public function all(Request $request):  JsonResponse
     {
        $query = $this->getAllCash();
        $query->orderBy('id', 'DESC')->get();
        return $this->successResponse($query);
     }
     public function getFilteredCash($params){
        $query = $this->getAllCash();
        if (isset ( $params['keyword']) ) {
           
            $query->where('store_address', 'LIKE', '%' . $params['keyword'] . '%');
                    
        }

       
        if (isset ( $params['store_id'])) {
            $query->where('store_id', $params['store_id']);
        }
        return $query;
     }
     public function addTransactionsToCash($item){
        $transactions =  Transaction::where('cash_id', $item->id)->get(); 
        $transactionsArray = $transactions->toArray();

        usort($transactionsArray, function($a, $b) {
            return $b['id'] - $a['id']; 
        }); 
        $item->transactions = $transactionsArray;
        $sum = $transactions->sum(function ($transaction) {
        
                return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        });
        $item->current_balance = $sum + $item->opening_balance;
  
        return $item;
     }
    public function index(Request $request): JsonResponse
    {  
        
      
 
        $params = $request->all();
        $query =  $this->getFilteredCash($params);
 

        $paginator = $query->orderBy('cash.id', 'DESC')->paginate(config('app.paginate', 20));

        $paginator->getCollection()->transform(function ($item) {
            return $this->addTransactionsToCash($item);
        });
        return $this->successResponse($paginator);
      
    }

    /**
     * @param Cash $cash
     * @return JsonResponse
     */
    public function show(Cash $cash): JsonResponse
    {
        
        $cash->load(['transactions']);
        $sum = $cash->transactions->sum(function ($transaction) {
            return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        }) ;
        $sum +=  $cash->opening_balance;
       
        $cash->current_balance = $sum;
    
        return $this->successResponse($cash);
        
        
    }

  
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
      
        $data = $request->all();
        $instance =  Cash::create($data);
        return $this->successResponse($instance);
    }

    /**
     * @param Request $request
     * @param Cash $cash
     * @return JsonResponse
     */
    public function update(Request $request, Cash $cash): JsonResponse
    {
      
        $data = $request->all();
        // dd($data);
        $this->changeCurrentBalance($request,$cash);
        $cash->update($data);
   
        return $this->successResponse();
       
       
    }
   

    private function changeCurrentBalance(Request $request,$cash )
    {
           
        $new_balance = $request->current_balance;
        
        $cash->load(['transactions']);
        $old_balance  = $cash->transactions->sum(function ($transaction) {
            return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        }) ;
   
        $old_balance +=  $cash->opening_balance;
    
        $increase_balance = $new_balance - $old_balance;
       
        if ($increase_balance == 0){return;}
        if ($increase_balance > 0){
            $note = 'Điều chỉnh tăng số dư hiện tại';
            $type = 'in';
        } else {
            $note = 'Điều chỉnh giảm số dư hiện tại';
            $type = 'out';
        }
        
        $dataTransaction = [
            'name' => 'balance:adjust',
            'type' => $type,
            'value' =>  abs($increase_balance),
            'note' => $note ,
            'user_id' => Auth::id(),
            
            'payment_method'=>1,
            'cash_id' => $request->get('id'),
            
        ];
   
        Transaction::create($dataTransaction);
        
    }

    /**
     * @param Cash $cash
     * @return JsonResponse
     */
    public function destroy(Cash $cash): JsonResponse
    {
        // $cash->delete();
		Cash::where('id', $cash->id)->update(['status' => 'Inactive']);
        return $this->successResponse();
    }
}


 



