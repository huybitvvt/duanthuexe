<?php

namespace App\Exports;

use App\Models\Cash;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use App\Http\Controllers\CashController;

class CashExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    protected $params;
    protected $cashController;
     

    public function __construct($params, CashController $cashController)
    {
        $this->cashController = $cashController;
        $this->params = $params;
    }
   
    public function collection(): Collection
    {
        
        
         $query =  $this->cashController->getFilteredCash($this->params);
         
            
             $collection= $query->get()->transform(function($item){
               $item = $this->cashController->addTransactionsToCash($item);
               unset($item['store_id']);
               unset($item['transactions']);
               
            return $item;
            });
            //    dd($collection->toArray());
        return $collection;
       
    }
    public function headings(): array
    {
        return [
            'id','Số dư ban đầu','Ngày tạo','Ngày cập nhật',  'Cửa hàng', 'Số dư hiện tại'
        ];
    }
}

