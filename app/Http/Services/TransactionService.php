<?php

namespace App\Http\Services;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Models\Bank;
use App\Exceptions\CustomException;
use App\Models\Cash;

class TransactionService
{
    
    protected $transactionModel;

    
    private $transactionRepository;

    public function __construct(Transaction $transactionModel,TransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
        $this->transactionModel = $transactionModel;
    }

    public function getList(Request $request)
    {
        $params = $request->all();
      $query=  $this->getListByParams($params);
       

        return $query->orderBy('transactions.id', 'DESC')
        ->select('transactions.*', 'banks.bank_name','banks.account_number','banks.owner_name','users.id as user_id', 'users.name as user_name')
        ->paginate(config('app.paginate', 20));
    }
    public function getListByParams($params){
        $query = $this->transactionRepository
        ->leftJoin('banks', 'transactions.bank_id', '=', 'banks.id')
        ->leftJoin('users', 'transactions.user_id', '=', 'users.id');
        if (isset($params['store_id'])){
            $store_id = $params['store_id'];
            if ($store_id) {
                $query->where('transactions.store_id', intval($store_id));
            }
        }
      
        if (isset($params['payment_method'] )){
            $payment_method = $params['payment_method'] ;
            if ($payment_method) {
                $query->where('transactions.payment_method', intval($payment_method));
            }
        }

        if (isset( $params['start_date'])){
            $start_date = $params['start_date'] ;  
            if (  $start_date) {
            
                $query = $query->whereDate('transactions.created_at', '>=', $start_date);
            }
        }
    
        if (isset($params['end_date'] )){
            $end_date =  $params['end_date'] ;  
            if ($end_date) {
                $query = $query->whereDate('transactions.created_at', '<=', $end_date);
            }
        }
       
        return $query;
    }
    protected function calcuVal($item) {
        $val_in = 0;
        $val_out = 0;
        $val_addon = 0;
        
        switch($item->type) {
            case Transaction::THU:
                $val_in = $item->value;
                break;
            case Transaction::CHI:
                $val_out = $item->value;
                break;
            case Transaction::ADDON:
                $val_addon = $item->value;
                break;
            default:
                break;
        }

        return [
            'val_in' => $val_in,
            'val_out' => $val_out,
            'val_addon' =>  $val_addon
        ];
    }

    public function stats(Request $request) {
        $query = $this->transactionModel->query();
        
        $store_id = $request->get('store_id', '');
        if ($store_id) {
            $query = $query->where('store_id', $store_id);
        }

        $payment_method = $request->get('payment_method', null);
        if ($payment_method) {
            $query = $query->where('payment_method', $payment_method);
        }

        $start_date = $request->get('start_date');
        if ($start_date) {
            $query = $query->whereDate('created_at', '>=', $start_date);
        }

        $end_date = $request->get('end_date');
        if ($end_date) {
            $query = $query->whereDate('created_at', '<=', $end_date);
        }

        $nodes = $query->get();
        $bank_ids = array();
        $all_in = 0;
        $all_out = 0;
        $all_addon = 0;

        $cash = [
            'type_in' => 0,
            'type_addon'=> 0,
            'type_out' => 0,
        ];

        $banks = array();
        for ($i = 0; $i < count($nodes); $i++) {
            $node = $nodes[$i];
            $res = $this->calcuVal($node);
            
            if (is_null($node->payment_method) || $node->payment_method == 1 ) {
                $cash['type_in'] += $res['val_in'];
                $cash['type_addon'] += $res['val_addon'];
                $cash['type_out'] += $res['val_out'];
            } else if (!is_null($node->bank_id)) {
                $key = $node->bank_id;
                if (empty($banks[$key])) {
                    $banks[$key] = [
                        'type_in' => 0,
                        'type_addon' => 0,
                        'type_out' => 0
                    ];
                }
                $banks[$node->bank_id]['type_in'] += $res['val_in'];
                $banks[$node->bank_id]['type_addon'] += $res['val_addon'];
                $banks[$node->bank_id]['type_out'] += $res['val_out'];
                array_push($bank_ids, $node->bank_id);
            }

            $all_in += $res['val_in'];
            $all_addon += $res['val_addon'];
            $all_out += $res['val_out'];

        }
        $bank_ids = array_unique($bank_ids);
        $list_bank = array();
        if (count($bank_ids) > 0) {
            $list_bank = Bank::query()->whereIn('id', $bank_ids)->get();
        }

        for ($i = 0; $i < count($list_bank); $i++) {
            $bank = $list_bank[$i];
            $map = $banks[$bank->id];
            if (!empty($map)) {
                $bank['type_in'] = $map['type_in'];
                $bank['type_out'] = $map['type_out'];
                $bank['type_addon'] = $map['type_addon'];
            }
        }

        return [
            'all_in' => $all_in,
            'all_addon'=> $all_addon,
            'all_out'=> $all_out,
            'cash_in' => $cash['type_in'],
            'cash_out' => $cash['type_out'],
            'cash_addon' => $cash['type_addon'],
            'banks' => $list_bank
        ];
    }

    public function stats2()
    {
        // $cashStats = $this->getStatsByPaymentMethod([1,null]);
        // $bankTransferStats = $this->getStatsByPaymentMethod([2]);
        // $combinedStats = $this->getStatsByPaymentMethod([1,2,null]);

        $q = $this->transactionModel->query();
        $bank_ids = $q->select('bank_id')->whereNotNull('bank_id')->groupBy('bank_id')->get();
        $b_ids = array();
        foreach ($bank_ids as $item) {
            array_push($b_ids, $item['bank_id']);
        }

        $bank_query = Bank::query();
        $banks = $bank_query->whereIn('id', $b_ids)->get();
        
        $all_in = 0;
        $all_out = 0;
        $all_addon = 0;
        foreach ($banks as $bank) {
            $bank['type_in'] = $this->getAmountByType($bank['id'], Transaction::THU, 2)->get()->sum('value');
            $bank['type_out'] = $this->getAmountByType($bank['id'], Transaction::CHI, 2)->get()->sum('value');
            $bank['type_addon'] = $this->getAmountByType($bank['id'], Transaction::ADDON, 2)->get()->sum('value');

            $all_in += $bank['type_in'];
            $all_out += $bank['type_out'];
            $all_addon += $bank['type_addon'];
        }

        $cash['type_in'] = $this->getAmountByType(null, Transaction::THU, 1)->get()->sum('value');
        $cash['type_out'] = $this->getAmountByType(null, Transaction::CHI, 1)->get()->sum('value');
        $cash['type_addon'] = $this->getAmountByType(null, Transaction::ADDON, 1)->get()->sum('value');

        $all_in += $cash['type_in'];
        $all_out += $cash['type_out'];
        $all_addon += $cash['type_addon'];

        return [
            'banks' => $banks,
            'all_in' => $all_in,
            'all_out' => $all_out,
            'all_addon' => $all_addon,
            'cash_in' => $cash['type_in'],
            'cash_out' => $cash['type_out'],
            'cash_addon' => $cash['type_addon']
        ];

    }
    public function store(Request $request)
    {
        return $this->transactionRepository->store($request);
    }

    public function update(Request $request, Transaction $tran)
    {
        return $this->transactionRepository->edit($request, $tran);
    }

    protected function getStatsByPaymentMethod($paymentMethods) {
        $query = $this->transactionModel->query();
        $query->whereIn('payment_method', $paymentMethods);
        if (in_array(null, $paymentMethods)) {
            $query->orWhereNull('payment_method');
        }
        $transactions = $query->get();
        $count = $transactions->count();
        $amount = $transactions->sum('value');
    
        return [
            'count' => $count,
            'amount' => $amount
        ];
    }

    protected function getAmountByType2($bank_id, $type, $payment_method) 
    {
        $query = $this->transactionModel->query();
        if (!is_null($bank_id)) {
            $query->where('bank_id', $bank_id);
        }

        return $query->where('type', $type)
        ->where('payment_method', $payment_method);
    }

    protected function getAmountByType($query, $bank_id, $type, $payment_method) 
    {
        $q = $query;
        if (!is_null($bank_id)) {
            $q->where('bank_id', $bank_id);
        }

        return $q->where('type', $type)
        ->where('payment_method', $payment_method);
    }
    public function processPaymentMethod( $payment_method, $bank_id, $store_id ){
		$result = [
			'cash_id' => null,
			'bank_id' => null,
		];
		$cashRecord = Cash::where('store_id', $store_id)->where("status", 'Active')->first();

		if ( in_array( $payment_method, array( 1, 3 ) ) ) {
			if ( $cashRecord && $cashRecord->id ) {
				$result['cash_id'] = $cashRecord->id;
			}
		}
		if ( in_array( $payment_method, array( 2, 3 ) ) ) {
			if ( $bank_id ) {
				$result['bank_id'] = $bank_id;
			}
		}
		return $result;
                
    }
}
