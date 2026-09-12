<?php

namespace App\Exports;

use App\Entities\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
 
class CustomersExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    public function collection(): Collection
    {
       return Customer::select( 'name', 'email', 'phone','id_card','address','warning')->where('status',1)->get(); 
    }
    public function headings(): array
    {
        return [
            'Tên', 'Email' ,'SĐT','Số CMTND/CCCD','Địa chỉ','Cảnh báo'
        ];
    }
}

