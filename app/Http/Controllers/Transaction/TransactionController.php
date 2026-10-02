<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Services\TransactionService;
use App\Models\Transaction;
use App\Models\SepayWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Helpers\DateTimeHelper;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'name' => 'required|string|max:191',
            'type' => 'required|in:in,out,addon',
            'value' => 'required|numeric|min:0|max:1000000000000',
            'payment_method' => 'required|integer|in:1,2,3',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'cash_id' => 'nullable|integer|exists:cash,id',
            'created_at' => 'nullable|date',
        ]);
        \App\Support\PermissionAccess::can($request->user(), 'finance.transaction.manage', (int) $request->store_id);
        $tran = $id !== null ? Transaction::findOrFail($id) : null;
        if ($tran !== null) {
            \App\Support\PermissionAccess::can($request->user(), 'finance.transaction.manage', (int) $tran->store_id);
            $this->ensureNotSepay($tran);
            $this->transactionService->update($request, $tran);
            return $this->successResponse();
        } else {
            $request->merge(['user_id' => $request->user()->id]);
            $tran = $this->transactionService->store($request);
            return $this->successResponse($tran);
        }
    }
    public function destroy(Request $request, $id){
		$transaction = Transaction::findOrFail($id);
		$this->ensureNotSepay($transaction);

		// if ($transaction->type === 'addon') {
		//     $order = Order::find($transaction->order_id);
		//     $this->orderService->changeHandlerPriceByAddonPrice($order,-$transaction->value);
		// }
		
		$transaction->delete();
		return $this->successResponse(null,"Xóa gia hạn thành công.");
    }

    private function ensureNotSepay(Transaction $transaction): void
    {
        if (Schema::hasTable('sepay_webhook_events')
            && SepayWebhookEvent::where('transaction_id', $transaction->id)
                ->orWhere('excess_transaction_id', $transaction->id)->exists()) {
            throw ValidationException::withMessages(['transaction' => 'Giao dịch SePay không thể sửa hoặc xóa thủ công.']);
        }
    }

	public function updateNew(Request $request) {
		$id = $request->get('id', 0);
		$created_at = $request->get('created_at', '');
		if ( is_numeric( $id ) && $id > 0 && ! empty( $created_at ) ) {
			$transaction = Transaction::findOrFail($id);
			$this->ensureNotSepay($transaction);
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
