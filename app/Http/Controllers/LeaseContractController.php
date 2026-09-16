<?php

namespace App\Http\Controllers;

use App\Exports\LeaseDebtExport;
use App\Http\Services\LeaseContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeaseContractController extends Controller
{
    protected $leaseService;

    public function __construct(LeaseContractService $leaseService)
    {
        $this->leaseService = $leaseService;
    }

    /**
     * Get paginated list of lease-to-own contracts with calculated debt and aging status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $contracts = $this->leaseService->index($request->all(), $user);
        return $this->successResponse($contracts);
    }

    /**
     * Get overall debt statistics and aging buckets.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->leaseService->getStats($request->all(), $user);
        return $this->successResponse($stats);
    }

    /**
     * Show single lease contract details with full installment periods.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contract = $this->leaseService->show($id);
            return $this->successResponse($contract);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Create a new lease-to-own contract and schedule installments.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'customer_id' => 'nullable|integer',
            'customer' => 'nullable|array',
            'vehicle_id' => 'nullable|integer',
            'store_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'installment_count' => 'required|integer|min:1',
            'period_amount' => 'nullable|numeric|min:0',
            'assigned_user_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        try {
            $contract = $this->leaseService->createContract($request->all(), $user);
            return $this->successResponse($contract, 'Tạo hợp đồng thuê sở hữu và lịch trả góp thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Collect payment and allocate to installment schedule.
     */
    public function allocatePayment(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|integer',
            'bank_id' => 'nullable|integer',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'installment_id' => 'nullable|integer',
        ]);

        try {
            $result = $this->leaseService->allocatePayment($id, $request->all(), $user);
            return $this->successResponse($result, 'Đã thu tiền và phân bổ vào kỳ thanh toán thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Add a debt collection note and appointment date.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'note_content' => 'required|string',
            'appointment_date' => 'nullable|date',
            'debt_classification' => 'nullable|string',
        ]);

        try {
            $note = $this->leaseService->addDebtNote($id, $request->all(), $user);
            return $this->successResponse($note, 'Đã thêm ghi chú nhắc nợ thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Export lease-to-own debt report to Excel.
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $fileName = 'bao-cao-cong-no-thue-so-huu-' . date('Ymd_His') . '.xlsx';
        return Excel::download(new LeaseDebtExport($request->all(), $user, $this->leaseService), $fileName);
    }
}
