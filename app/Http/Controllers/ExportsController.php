<?php

namespace App\Http\Controllers;

 
use App\Exports\CustomersExport;
use App\Exports\ReportExport;
use App\Exports\BankExport;
use App\Exports\VehicleExport;
use App\Exports\VehicleRevenueExport;
use App\Exports\TransactionExport;
use App\Exports\OrderExport;
use App\Exports\CashExport;
use Maatwebsite\Excel\Facades\Excel;  
use Illuminate\Http\Request;   
use Illuminate\Support\Facades\Auth;
use App\Support\PilotAccess;
 

class ExportsController extends Controller
{
 
    public function customers()
    {   
      
        $file = Excel::download(new CustomersExport, 'customers.xlsx'); 
        return  $file ;
 
    }
    public function vehicles(Request $request){
         
       $params = $request->all();
 
        $vehicleExport = app()->make(VehicleExport::class, ['params' => $params]);
         
    
        $file = Excel::download( $vehicleExport , 'file.xlsx'); 

     
     
        return  $file ;
 
    }
    public function transactions(Request $request){
        $params = $request->all();
        $export = app()->make(TransactionExport::class, ['params' => $params]);
        $fileName = 'lich-su-thu-chi-' . date('Ymd_His') . '.xlsx';
        return Excel::download($export, $fileName);
    }
     public function orders(Request $request){
        $params = $request->all();
        $user = Auth::user();
        if ($user && !PilotAccess::isAdmin($user)) {
            $params['store_id'] = $user->store_id;
        }
        $export = app()->make(OrderExport::class, ['params' => $params]);
        $from = $request->input('start_date', 'all');
        $to = $request->input('end_date', 'all');
        $file = Excel::download($export, "hop-dong_{$from}_{$to}.xlsx");
        return  $file ;
  
     }
    public function generalReports(Request $request){
        $params = $request->all();
        $export = app()->make(ReportExport::class, ['params' => $params]);
        $fileName = 'bao-cao-thue-xe-' . date('Ymd_His') . '.xlsx';

        return Excel::download($export, $fileName);
    }
    public function vehicleRevenue(Request $request){
        $params = $request->all();
        $export = app()->make(VehicleRevenueExport::class, ['params' => $params]);
        $file = Excel::download( $export , 'file.xlsx');
        return  $file ;
    }
    public function banks(Request $request){
        $params = $request->all();
        $export = app()->make(BankExport::class, ['params' => $params]);
        $file = Excel::download( $export , 'file.xlsx');
        return  $file ;
    }
    public function cash(Request $request){
        $params = $request->all();
        $export = app()->make(CashExport::class, ['params' => $params]);
        $file = Excel::download( $export , 'file.xlsx');
        return  $file ;
    }
}


