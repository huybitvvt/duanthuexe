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
use ZipArchive; 
use Illuminate\Support\Facades\Storage;
 

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
        $file = Excel::download( $export , 'file.xlsx');
        return  $file ;
  
     }
     public function orders(Request $request){
        $params = $request->all();
        $export = app()->make(OrderExport::class, ['params' => $params]);
        $file = Excel::download( $export , 'file.xlsx');
        return  $file ;
  
     }
     public function generalReports(Request $request){
        
        $params = $request->all();
        $reportExport = app(ReportExport::class,['params' => $params])  ;
        $countReport = $reportExport->countReport();
        
   
        $zip = new ZipArchive();
        
 
        $zipFilePath = storage_path('app/exports/files.zip');
        
    
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === true) {
          
            for ($i = 0; $i < $countReport; $i++) {
           
                $export = app()->make(ReportExport::class, ['params' => $params, 'reportIndex' => $i]);
     
                $file = Excel::download($export, "file{$i}.xlsx");
         
                $zip->addFromString("report-{$i}.xlsx", file_get_contents($file->getFile()));
            }
        
            
            $zip->close();
        
   
            return response()->download($zipFilePath)->deleteFileAfterSend(true);
        } else {
         
            return response()->json(['error' => 'Unable to create the zip file'], 500);
        }


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



