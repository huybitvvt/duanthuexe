<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\SepayWebhookEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Http\Services\TransactionService;
use Illuminate\Support\Facades\DB;
use App\Helpers\DateTimeHelper;

class ReceiptController extends Controller
{
    protected $transactionService;
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $transaction->load([ 'bank', 'store:id,store_name','user:id,name']);
 
        return $this->successResponse( $transaction);
    }
    public function putOrPost(Request $request, $id = null): JsonResponse
    {
        $user = $request->user();
        if (!$request->filled('store_id') && $user->store_id) {
            $request->merge(['store_id' => $user->store_id]);
        }
        $data = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'type' => 'required|in:in,out',
            'payment_method' => 'required|integer|in:1,2,3',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'cash_amount' => 'nullable|numeric|min:0|max:1000000000000',
            'bank_transfer_amount' => 'nullable|numeric|min:0|max:1000000000000',
            'created_at' => 'nullable|date',
            'order_id' => 'nullable|integer|exists:orders,id',
            'note' => 'nullable|string|max:5000',
        ]);
        \App\Support\PermissionAccess::can($user, 'finance.transaction.manage', (int) $data['store_id']);
        $method = (int) $data['payment_method'];
        $cash = in_array($method, [1, 3], true) ? (float) ($data['cash_amount'] ?? 0) : 0;
        $transfer = in_array($method, [2, 3], true) ? (float) ($data['bank_transfer_amount'] ?? 0) : 0;
        if ($cash + $transfer <= 0) {
            throw ValidationException::withMessages(['cash_amount' => 'Số tiền thanh toán phải lớn hơn 0.']);
        }
        if ($transfer > 0 && empty($data['bank_id'])) {
            throw ValidationException::withMessages(['bank_id' => 'Chọn tài khoản nhận chuyển khoản.']);
        }
        $accounts = $this->transactionService->processPaymentMethod($method, $data['bank_id'] ?? null, $data['store_id']);
        if ($cash > 0 && empty($accounts['cash_id'])) {
            throw ValidationException::withMessages(['store_id' => 'Cơ sở chưa có két hoạt động.']);
        }
        if (!empty($data['created_at'])) {
            $data['created_at'] = DateTimeHelper::parse($data['created_at']);
        }
        unset($data['cash_amount'], $data['bank_transfer_amount']);
        $data['name'] = 'receipt';
        try {
            DB::transaction(function () use ($data, $cash, $transfer, $accounts, $id, $user) {
                $existing = $id ? Transaction::lockForUpdate()->findOrFail($id) : null;
                if ($existing) {
                    $this->ensureNotSepay($existing);
                    \App\Support\PermissionAccess::can($user, 'finance.transaction.manage', (int) $existing->store_id);
                    if ($existing->name !== 'receipt') {
                        throw ValidationException::withMessages(['receipt' => 'Giao dịch hợp đồng không thể sửa bằng phiếu thu chi.']);
                    }
                }
                $data['user_id'] = $existing ? $existing->user_id : $user->id;
                $parts = [];
                if ($cash > 0) {
                    $parts[] = ['value' => $cash, 'payment_method' => 1, 'cash_id' => $accounts['cash_id'], 'bank_id' => null];
                }
                if ($transfer > 0) {
                    $parts[] = ['value' => $transfer, 'payment_method' => 2, 'cash_id' => null, 'bank_id' => $accounts['bank_id']];
                }
                foreach ($parts as $part) {
                    $row = array_merge($data, $part);
                    if ($existing) {
                        $existing->update($row);
                        $existing = null;
                    } else {
                        Transaction::create($row);
                    }
                }
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            \Illuminate\Support\Facades\Log::error('Unable to save receipt.', ['exception' => get_class($exception)]);
            return $this->errorResponse('Không thể lưu phiếu thu chi. Vui lòng thử lại.', 500);
        }
        return $this->successResponse('', 'Lưu phiếu thu chi thành công');
    }
    public function index(Request $request):  JsonResponse
    {
        
        $keyword = $request->get('keyword', '');
        $user = auth()->user();
        $receipts = Transaction::where('name', 'receipt')
        ->with([ 'bank', 'store:id,store_name','user:id,name',  'activityLogs' => function ($query) {
            $query->with('user:id,name')->orderBy('id', 'desc');
        } ]);
        if ($payment_method = $request->get('payment_method')) {
            $receipts->where('payment_method', $payment_method);
        }
        if ($store_id = $request->get('store_id')) {
            $receipts->where('store_id', $store_id);
        }
        if (!\App\Support\PermissionAccess::allows($user, 'accounting.view')) {
            $receipts->where('store_id', $user->store_id);
        }
        if ($request->filled('start_date')) {
            $start_date = $request->get('start_date');
            $receipts->where('created_at', '>=', DateTimeHelper::parse($start_date)->startOfDay());
        }
        if ($request->filled('end_date')) {
            $end_date = $request->get('end_date');
            $receipts->where('created_at', '<=', DateTimeHelper::parse($end_date)->endOfDay());
        }
 
        if ($keyword) {
            $receipts->where('note', 'LIKE', '%' . $keyword . '%');
        }

        $receipts = $receipts->orderBy('id', 'desc')->paginate(config('app.paginate', 20));        
       
        return $this->successResponse( $receipts);
    }
    public function destroy(Transaction $transaction): JsonResponse
    {
      
        $this->ensureNotSepay($transaction);
        try {
            DB::beginTransaction();
            $transaction->delete();
            DB::commit();
            return $this->successResponse('', 'Xóa thành công');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    private function ensureNotSepay(Transaction $transaction): void
    {
        if (Schema::hasTable('sepay_webhook_events')
            && SepayWebhookEvent::where('transaction_id', $transaction->id)
                ->orWhere('excess_transaction_id', $transaction->id)->exists()) {
            throw ValidationException::withMessages(['transaction' => 'Phiếu thu SePay không thể sửa hoặc xóa thủ công.']);
        }
    }
}
