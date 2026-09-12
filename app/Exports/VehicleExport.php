<?php

namespace App\Exports;

use App\Models\Vehicle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use App\Repositories\VehicleRepository ;

class VehicleExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    protected $params;
    protected $vehicleRepo;
     

    public function __construct($params,VehicleRepository $vehicleRepo)
    {
        $this->vehicleRepo = $vehicleRepo;
        $this->params = $params;
    }
   
    public function collection(): Collection
    {
        

        $query = $this->vehicleRepo;
   
        if ($this->params){
            $query->filter($this->params);
        }
        $query->join('stores', 'vehicles.store_id', '=', 'stores.id');
 
  
       $query->select('name', 'brand', 'type','year','license','color','store_name','cost_price','sale_price','vehicles.status' ,'vehicles.created_at');
      return   $query->orderBy('vehicles.id', 'DESC')->get(); 
 
       
    }
    public function headings(): array
    {
        return [
            'Tên', 'Brand', 'Loại xe','Đời xe','Biển số','Màu sắc','Cửa hàng','Giá mua','Giá bán','Trạng thái' ,'Ngày tạo'
        ];
    }
}

