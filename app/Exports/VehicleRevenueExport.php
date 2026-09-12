<?php

namespace App\Exports;

use App\Models\Vehicle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Http\Services\VehicleService;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
 

class VehicleRevenueExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    protected $params;
    protected $vehicleService;
     

    public function __construct($params,VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
        $this->params = $params;
    }
   
    public function collection(): Collection
    {
        
        $items = $this->vehicleService->indexWithRevenue($this->params,  true);

       $items = $items->each(function($item){
        unset($item['price_range']);
        unset($item['store']);
        unset($item['store_id']);
        unset($item['price_min']);
        unset($item['price_max']);
        unset($item['type_of_service_id']);
        unset($item['order_vehicle_details']);
        unset($item['created_by']);
        unset($item['updated_at']);
       }); 
    //    dd($items->toArray());
      return $items;
 
       
    }
    public function headings(): array
    {
        return [
           'id', 'Tên', 'Brand', 'Loại xe','Đời xe','Biển số',"chassis" ,  "engine" ,'Trạng thái','Giá mua','Giá bán', 'Ngày tạo','Màu sắc','Số order','Doanh thu'
        ];
    }
}

