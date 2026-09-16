<?php

namespace App\Http\Controllers;

use App\Http\Services\AccountingService;
use App\Models\AccountingVatDocument;
use App\Models\BusinessAsset;
use App\Support\PilotAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    private $service;

    public function __construct(AccountingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);
        $storeId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;
        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($storeId !== null && $storeId !== (int) $user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem kế toán của cơ sở khác.', 403);
            }
            $storeId = (int) $user->store_id;
        }

        return $this->successResponse($this->service->dashboard([
            'start_date' => $validated['start_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString(),
            'end_date' => $validated['end_date'] ?? Carbon::now('Asia/Ho_Chi_Minh')->toDateString(),
            'store_id' => $storeId,
        ]));
    }

    public function saveVatDocument(Request $request): JsonResponse
    {
        $user = Auth::user();
        PilotAccess::admin($user);
        $id = $request->input('id');
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:accounting_vat_documents,id',
            'document_type' => 'required|in:input,output',
            'invoice_number' => [
                'required', 'string', 'max:100',
                Rule::unique('accounting_vat_documents', 'invoice_number')
                    ->where(function ($query) use ($request) {
                        return $query->where('document_type', $request->input('document_type'));
                    })->ignore($id),
            ],
            'invoice_date' => 'required|date_format:Y-m-d',
            'counterparty_name' => 'required|string|max:200',
            'tax_code' => 'nullable|string|max:50',
            'amount_before_tax' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'payment_status' => 'required|in:unpaid,partial,paid',
            'store_id' => 'nullable|integer|exists:stores,id',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->successResponse(
            $this->service->saveVatDocument($validated, $user->id, $id ? (int) $id : null),
            'Đã lưu chứng từ VAT.'
        );
    }

    public function saveAsset(Request $request): JsonResponse
    {
        $user = Auth::user();
        PilotAccess::admin($user);
        $id = $request->input('id');
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:business_assets,id',
            'asset_code' => ['required', 'string', 'max:60', Rule::unique('business_assets', 'asset_code')->ignore($id)],
            'name' => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'store_id' => 'nullable|integer|exists:stores,id',
            'purchase_date' => 'nullable|date_format:Y-m-d',
            'purchase_cost' => 'required|numeric|min:0',
            'residual_value' => 'nullable|numeric|min:0',
            'depreciation_months' => 'required|integer|min:0|max:1200',
            'status' => 'required|in:active,disposed,maintenance',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->successResponse(
            $this->service->saveAsset($validated, $user->id, $id ? (int) $id : null),
            'Đã lưu tài sản.'
        );
    }

    public function deleteVatDocument(int $id): JsonResponse
    {
        PilotAccess::admin(Auth::user());
        AccountingVatDocument::findOrFail($id)->delete();
        return $this->successResponse(null, 'Đã xóa chứng từ VAT.');
    }

    public function deleteAsset(int $id): JsonResponse
    {
        PilotAccess::admin(Auth::user());
        BusinessAsset::findOrFail($id)->delete();
        return $this->successResponse(null, 'Đã xóa tài sản.');
    }
}
