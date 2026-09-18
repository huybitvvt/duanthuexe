<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Auth;
use App\Models\Bank; 
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class CustomerRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class BankRepositoryEloquent extends BaseRepository implements BankRepository
{

    
    

 
    /**
     * Specify Model class name
     *
     * @return string
     */



    public function model()
    {
        return Bank::class;
    }


    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $params = $request->all();
        $banks = $this->getBanks($params);

        $paginator = $banks->orderBy('banks.id', 'DESC')->paginate(config('app.paginate', 20));
        $bankIds = $paginator->getCollection()->pluck('id');
        $balances = Transaction::whereIn('bank_id', $bankIds)
            ->select('bank_id')
            ->selectRaw("SUM(CASE WHEN type = 'out' THEN -value ELSE value END) AS balance")
            ->groupBy('bank_id')
            ->pluck('balance', 'bank_id');

        $paginator->getCollection()->transform(function($bank) use ($balances) {
            $bank->current_balance = (float) $bank->opening_balance + (float) $balances->get($bank->id, 0);
            return $bank;
        });

        return $paginator;
        
        
    }
  
    public function   addTransactionToBank($bank) {
        $transactions = $bank->relationLoaded('transactions')
            ? $bank->transactions
            : Transaction::where('bank_id', $bank->id)->get();
        $transactions = $transactions->sortByDesc('id')->values();
        $bank->setRelation('transactions', $transactions);
        $sum = $transactions->sum(function ($transaction) {
        
                return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        });
        $bank->current_balance = $sum + $bank->opening_balance;
  
        return $bank;
    }
    public function getBanks($params){
        $banks = $this->getModel()->newQuery();
        
        $banks->leftJoin('stores', 'banks.store_id', '=', 'stores.id')->select('banks.*', DB::raw("CASE WHEN banks.store_id = 0 THEN 'Cửa hàng tổng' ELSE stores.store_name END as store_name"))->where(function ($query) {
			$query->where('banks.store_id', '=', 0)->orWhereNotNull('stores.id');
		});

		$banks->where("banks.status", "Active");

        $user = auth()->user();
        if ($user->role_rel->slug !== 'quan-tri-vien') {
            $banks->where('banks.store_id', $user->store_id);
        }


        // search
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $banks->where(function ($query) use ($keyword) {
                $query->where('banks.owner_name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('banks.account_number', $keyword);
            });
        }
		
		if ( isset( $params['account_type'] ) ) {
			if ( in_array( intval( $params['account_type'] ), array(0, 1, 2) ) ) {
				$banks->where( 'account_type', intval( $params['account_type'] ) );
			} else if ( 10 == intval( $params['account_type'] ) ) {
				$banks->whereIn( 'account_type', array( 0, 1 ) );
			} else if ( 20 == intval( $params['account_type'] ) ) {
				$banks->whereIn( 'account_type', array( 0, 2 ) );
			}
		}

     
        if ( isset($params['store_id'])  ) {
            $store_id = $params['store_id'];
            $banks->where('banks.store_id', $store_id);
        }
        // end search
        return $banks;
    }
    public function all($columns = ['*'])  
    {
    
        $banks = $this->getModel()->newQuery();
        $banks->join('stores', 'banks.store_id', '=', 'stores.id')->select('banks.*', 'stores.store_name');
		$banks->where("banks.status", "Active");
        $user = auth()->user();
        if ($user->role_rel->slug !== 'quan-tri-vien') {
            $banks->where('banks.store_id', $user->store_id);
        }
        // return $banks;
        return $banks->orderBy('id', 'DESC')->get()  ;
    }
    public function store(Request $request)
    {
        $data = $request->all();
        return $this->getModel()->newQuery()->create($data);

    }

    public function edit(Request $request, Bank $bank)
    {
        $data = $request->all();
     
        $this->changeCurrentBalance($request,$bank);
        $bank->update($data);
    }

    private function changeCurrentBalance(Request $request,$bank )
    {
           
        $new_balance = $request->current_balance;
        
        $bank->load(['transactions']);
        $old_balance  = $bank->transactions->sum(function ($transaction) {
            return $transaction->type === 'out' ? -1 * $transaction->value : $transaction->value;
        }) ;
        $old_balance +=  $bank->opening_balance;
    
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
            
            'payment_method'=>2,
            'bank_id' => $bank->id,
            
        ];
        Transaction::create($dataTransaction);
        
    }

}
