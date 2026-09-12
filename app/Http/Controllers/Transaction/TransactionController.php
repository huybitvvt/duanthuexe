<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Services\TransactionService;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Helpers\DateTimeHelper;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    private $transactionService;
    protected $orderService;
    public function __construct(TransactionService $transactionService,OrderService $orderService )
    {
        $this->transactionService = $transactionService;
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->transactionService->getList($request));
    }
    public function stats(Request $request)
    {
        return $this->successResponse($this->transactionService->stats($request));
    }
    
    /**
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */

    public function putOrPost(Request $request, $id = null): JsonResponse
    {
        $tran = Transaction::find($id);        
        if ($tran !== null) {
            $this->transactionService->update($request, $tran);
            return $this->successResponse();
        } else {
            $tran = $this->transactionService->store($request);
            return $this->successResponse($tran);
        }
    }
    public function destroy(Request $request, $id){
		$transaction = Transaction::findOrFail($id);

		// if ($transaction->type === 'addon') {
		//     $order = Order::find($transaction->order_id);
		//     $this->orderService->changeHandlerPriceByAddonPrice($order,-$transaction->value);
		// }
		
		$transaction->delete();
		return $this->successResponse(null,"Xóa gia hạn thành công.");
    }

	public function updateNew(Request $request) {
		$id = $request->get('id', 0);
		$created_at = $request->get('created_at', '');
		if ( is_numeric( $id ) && $id > 0 && ! empty( $created_at ) ) {
			$transaction = Transaction::findOrFail($id);
			$created_at_str = $created_at;
			$created_at = DateTimeHelper::parse($created_at);

			if ( $transaction && $created_at ) {
				$log = new ActivityLog();
				$log->name = 'transaction:' . $transaction->id;
				$log->order_id = $transaction->order_id;
				$log->user_id = Auth::id();
				$log->action = 'update';
				$log->content = 'Giao dịch #' . $transaction->id . ', sửa ngày giao dịch: ' . $transaction->created_at->format('d-m-Y H:i:s') . ' -> ' . $created_at_str;
				
				$transaction->update(['created_at' => $created_at]);
				$log->save();
				return $this->successResponse(null,"Cập nhật thành công");
			}
		}
		return $this->errorResponse('Dữ liệu không hợp lệ', 200);
	}
}
