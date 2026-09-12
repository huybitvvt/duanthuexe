<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
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
    public function putOrPost(Request $request,   $id = null): JsonResponse
    {
        try {
            $data = $request->all();

			$cash_amount = is_numeric( $data['cash_amount'] ) && $data['cash_amount'] > 0 ? $data['cash_amount'] : 0;
			$bank_transfer_amount = is_numeric( $data['bank_transfer_amount'] ) && $data['bank_transfer_amount'] > 0 ? $data['bank_transfer_amount'] : 0;

			if ( $cash_amount + $bank_transfer_amount == 0 ) {
				return $this->errorResponse( 'Số tiền thanh toán phải lớn hơn 0', 422 );
			}
     
            $user = Auth::user();
            $role = $user->role_id;
            if ($role !== 1){
                $data['store_id']  =  $user->store_id;
            }
            $result = $this->transactionService->processPaymentMethod( $data['payment_method'], $data['bank_id'], $data['store_id'] );
            $data['bank_id'] = $result['bank_id'];
            $data['cash_id'] = $result['cash_id'];
			$payment_method = $data['payment_method'];

			if ( is_numeric( $data['payment_method'] ) ) {
				$payment_method = intval( $data['payment_method'] );
				if ( 1 == $payment_method && $cash_amount > 0 ) {
					$data['value'] = $cash_amount;
				}
				if ( 2 == $payment_method && $bank_transfer_amount > 0 ) {
					$data['value'] = $bank_transfer_amount;
				}
			}

			if ( isset( $data['created_at'] ) && ! empty( $data['created_at'] ) ) {
				$input_date = DateTimeHelper::parse( $data['created_at'] );
				$data['created_at'] = $input_date;
				$data['updated_at'] = $input_date;
			}

            $tran = Transaction::find($id);  
            if ($tran !== null) {
                $tran->update($data);
            } else {
                $data['name'] = 'receipt';
                $data['user_id'] = Auth::id();

				if ( 3 == $payment_method ) { // This option include 2 payment method then need to split to 2 transactions.
					$data['payment_method'] = 3;
					if ( $cash_amount > 0 ) {
						$data['value'] = $cash_amount;
						$data['bank_id'] = null;
						$data['cash_id'] = $result['cash_id'];
						$tran = Transaction::create($data);
					}
					if ( $bank_transfer_amount > 0 ) {
						$data['value'] = $bank_transfer_amount;
						$data['bank_id'] = $result['bank_id'];
						$data['cash_id'] = null;
						$tran = Transaction::create($data);
					}
				} else {
					$tran = Transaction::create($data);
				}
            }
            return $this->successResponse('', 'Thêm mới thành công');
        } catch(\Exception $exception){
            return $this->errorResponse($exception->getMessage(), 422);
        }
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
        if ($user->role_rel->slug !== 'quan-tri-vien') {
            $receipts->where('store_id', $user->store_id);
        }
        if ($request->filled('start_date')) {
            $start_date = $request->get('start_date');
            $receipts = $receipts->whereDate('created_at', '>=', $start_date);
        }
        if ($request->filled('end_date')) {
            $end_date = $request->get('end_date');
            $receipts = $receipts->whereDate('created_at', '<=', $end_date);
        }
 
        if ($keyword) {
            $receipts->where('note', 'LIKE', '%' . $keyword . '%');
        }

        $receipts = $receipts->orderBy('id', 'desc')->paginate(config('app.paginate', 20));        
       
        return $this->successResponse( $receipts);
    }
    public function destroy(Transaction $transaction): JsonResponse
    {
      
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
}