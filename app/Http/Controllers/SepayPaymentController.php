<?php

namespace App\Http\Controllers;

use App\Http\Services\SepayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SepayPaymentController extends Controller
{
    private $payments;

    public function __construct(SepayPaymentService $payments)
    {
        $this->payments = $payments;
    }

    public function index(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        if ($storeId !== null && (!ctype_digit((string) $storeId) || (int) $storeId < 1)) {
            throw ValidationException::withMessages(['store_id' => 'Cơ sở không hợp lệ.']);
        }
        return $this->successResponse($this->payments->listRequests($request->user(), $storeId ? (int) $storeId : null));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => 'required|integer|min:1|exists:stores,id',
            'order_id' => 'nullable|integer|min:1',
            'purpose' => 'required|in:general,deposit,rental,additional_deposit,extension',
            'line_item_id' => 'required_if:purpose,extension|nullable|integer|min:1',
            'extension_return_at' => 'required_if:purpose,extension|nullable|date',
            'amount' => 'required|integer|min:1|max:2000000000',
            'note' => 'required|string|max:180',
        ]);

        return $this->successResponse($this->payments->createRequest($data, $request->user()), 'Đã tạo mã QR SePay.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->successResponse($this->payments->getRequest($id, $request->user()));
    }

    public function orderOptions(Request $request, int $id): JsonResponse
    {
        return $this->successResponse($this->payments->orderOptions($id, $request->user()));
    }

    public function unmatched(Request $request): JsonResponse
    {
        return $this->successResponse($this->payments->unmatched($request->user()));
    }

    public function webhook(Request $request): JsonResponse
    {
        if (!(string) config('services.sepay.webhook_api_key')) {
            throw new ServiceUnavailableHttpException(null, 'SePay chưa được cấu hình.');
        }
        if (!$this->payments->verifyWebhookKey($request->header('Authorization'))) {
            return response()->json(['success' => false], 401);
        }

        $payload = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'gateway' => 'required|string|max:100',
            'accountNumber' => 'required|string|max:30',
            'transferType' => 'required|in:in,out',
            'transferAmount' => 'required|integer|min:1|max:2000000000',
            'code' => 'nullable|string|max:100',
            'content' => 'nullable|string|max:2000',
            'referenceCode' => 'nullable|string|max:100',
        ])->validate();

        $result = $this->payments->processWebhook($payload);
        return response()->json(['success' => true, 'status' => $result['status']]);
    }
}
