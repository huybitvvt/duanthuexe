<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
 
 
use App\Repositories\OrderRepositoryEloquent;
 
class OrderExport implements FromCollection, WithHeadings , WithStrictNullComparison
{
    private $params;
    private $repo;
    
    public function __construct($params, OrderRepositoryEloquent $repo)
    {
        $this->repo = $repo;
        $this->params = $params;
    }
    public function collection(): Collection
    {
        $query1 = Order::query();
        $query = $this->repo->getOrderByParams($query1,$this->params);
        $query
        ->join('stores','stores.id','=','orders.store_id')
        ->join('customers', 'customers.id', '=', 'orders.customer_id')
        ->join('order_vehicle_details', 'order_vehicle_details.order_id', '=', 'orders.id')
        ->join('vehicles', 'vehicles.id', '=', 'order_vehicle_details.vehicle_id')
        ->select(
            'orders.id','orders.created_at',
     
        'customers.name  as customer_name'
        ,'customers.phone',
      
        'vehicles.name as vehicle_name',
        'vehicles.license',

        'order_vehicle_details.rent_at',
        'order_vehicle_details.return_at',
        'orders.note','orders.pid',
        'orders.total','orders.order_status'
        );
   
       
      
         return $query->orderBy('orders.created_at','DESC')   ->get();
         
      

    }
 
    public function headings(): array
    {
        return [
            'ID','Ngày tạo',  
            'Tên khách hàng',
            'SĐT khách hàng',
            'Tên xe','Biển số',
            'Thời gian mượn',
            'Thời gian trả',
            'Ghi chú','Đặt cọc',
            'Tạm tính','Trạng thái'
        ];
    }
}

