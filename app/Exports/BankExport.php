<?php

namespace App\Exports;

use App\Models\Bank;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use App\Repositories\BankRepository ;

class BankExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    protected $params;
    protected $bankRepo;
     

    public function __construct($params, BankRepository $bankRepo)
    {
        $this->bankRepo = $bankRepo;
        $this->params = $params;
    }
   
    public function collection(): Collection
    {
        
            $query = $this->bankRepo->getBanks($this->params);
            
             $collection= $query->get()->transform(function($bank){
               $bank = $this->bankRepo->addTransactionToBank($bank);
               unset($bank['store_id']);
               unset($bank['transactions']);
               
            return $bank;
            });
            //    dd($collection->toArray());
        return $collection;
       
    }
    public function headings(): array
    {
        return [
            'id','Tên ngân hàng','STK','Tên người thụ hưởng', 'Số dư đầu kì','Ngày tạo','Ngày cập nhật','Cửa hàng','Số dư hiện tại'
        ];
    }
}

