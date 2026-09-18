<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Services\ReportService;
use App\Http\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\TransactionRepository;
use App\Models\Transaction;
use App\Http\Services\TransactionService;
use App\Entities\Customer;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Helpers\CarRentalHelper;
use App\Http\Controllers\Transaction\TransactionController;
use App\Models\Vehicle;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AddOnOrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\VehicleRepository;
use App\Validators\OrderValidator;
use App\Repositories\OrderVehicleDetailRepositoryEloquent;
use App\Exceptions\CustomException;
use App\Models\Cash;
use App\Helpers\DateTimeHelper;

class ReportController extends Controller
{
    private $reportService;

    public function __construct(ReportService $reportService, OrderService $orderService, TransactionRepository $transactionRepository)
    {
        $this->reportService = $reportService;
        $this->orderService = $orderService;
        $this->transactionRepository = $transactionRepository;
    }

	public function detailReportNew(Request $request)
    {   
        $result = $this->reportService->handleDetailReportNew($request->all());
        return $this->successResponse($result);
    }

	public function detailReportDayByDay(Request $request)
    {   
        $result = $this->reportService->detailReportDayByDay($request->all());
        return $this->successResponse($result);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailReport(Request $request)
    {
        
        $result = $this->reportService->handleDetailReport($request->all());
        return $this->successResponse($result);
    }
    public function quickReport(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->reportService->quickOrderStats($request->all())
        );
    }
    public function orderStats($orders ): array
    {
        $orders->map(function ($order) {
            $transaction_out = $order->transactions->where('type', Transaction::CHI);
            $transaction_in = $order->transactions->where('type', Transaction::THU);
 
            $order->thu_price = 0;
            $order->refund_customer = 0;
 
            $order->refund_customer = $transaction_out->sum('value');
            $order->thu_price = $transaction_in->sum('value');
 
            return $order;
        });
        $total_order = $orders->count();
        $total_contracts_completed = $orders->where('order_status', OrderValidator::ORDER_COMPLETED)->count();
        $total_contracts_renting = $orders->where('order_status', OrderValidator::ORDER_RENTING)->count();
        $total_out_of_date = $orders->where('out_dated_at', '>', 0)->where('order_status', '!=', OrderValidator::ORDER_COMPLETED)->count();

        return [
            'total_order' => $total_order,
            'total_contracts_completed' => $total_contracts_completed,
            'total_contracts_renting' => $total_contracts_renting,
            'total_out_of_date' => $total_out_of_date,
 
        ];
    }
   
}
