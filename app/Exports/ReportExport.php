<?php

namespace App\Exports;
 
use App\Http\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
 
class ReportExport implements FromCollection, WithStrictNullComparison
{
    
    private $reportService ;
    private $params;
    private $reportIndex;
    
    public function __construct($params, $reportIndex = null, ReportService $reportService)
    {
        $this->reportService = $reportService;
        $this->params = $params;
        $this->reportIndex = $reportIndex;
    }
    
    public function collection(): Collection
    {
        $arr = $this->getReportArr();
 
       
        $keys = array_keys($arr);
        $values = array_values($arr);
        $sheet = $arr[ $keys [$this->reportIndex]];
   
        $newArr  = [];
        if ($keys[$this->reportIndex] =='all_stores'){
    
            $newArr = [$sheet];
            array_unshift($newArr,['Đặt cọc','Trả cọc','Lãi thuê','Tiền gia hạn','Tổng tiền thu thuê xe']);
            
        
        
        } else {
            $newArr = $sheet;
        
            array_unshift($newArr,['Ngày','Đặt cọc','Trả cọc','Lãi thuê','Tiền gia hạn','Tổng tiền thu thuê xe']);

           
        }
       
        array_unshift($newArr ,[$keys[$this->reportIndex]]);
        return new Collection($newArr);
    }

   public function countReport(){
        $arr = $this->getReportArr();
        return count($arr);
   }

   public function getReportArr(){
    $all_reports =  $this->reportService->handleDetailReport($this->params);
   
   
 
     $arr = $all_reports;
 
     return $arr;
   }
}

