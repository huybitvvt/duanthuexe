<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use   App\Http\Services\TransactionService;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
 
class TransactionExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    private $params;
    private $transactionService;
    
    public function __construct($params, TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        $this->params = $params;
    }
    public function collection(): Collection
    {
        $query = $this->transactionService->getListByParams($this->params);
       return $query->join('stores','stores.id','=','transactions.store_id') 
       ->select('banks.owner_name','banks.bank_name','banks.account_number', 'transactions.id', 'transactions.created_at', 'users.name','stores.store_name' ,'type','payment_method','value','note') 
     
       ->orderBy('transactions.created_at','DESC')->get()
       ->each(function ($transaction) {
        // Modify the payment_method field based on conditions
        if ($transaction->payment_method === 1 || $transaction->payment_method === null) {
            $transaction->payment_method = "Tiền mặt";
        } elseif ($transaction->payment_method === 2) {
            $transaction->payment_method = "Chuyển khoản" .' - '.$transaction->bank_name .' - '.$transaction->owner_name .' - '. $transaction->account_number;
        }
        unset($transaction->owner_name);
        unset($transaction->bank_name);
        unset($transaction->account_number);
        }); 
      

    }
    public function headings(): array
    {
        return [
            'Mã giao dịch', 'Ngày giao dịch' ,'Người thực hiện','Cửa hàng','Loại giao dịch','Phương thức','Số tiền','Ghi chú'
        ];
    }
}

