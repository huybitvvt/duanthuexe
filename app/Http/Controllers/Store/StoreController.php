<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Services\StoreService;
use App\Models\Store;
use App\Support\HimotoStores;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    private $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse($this->storeService->index($request->all()));
    }

    public function all(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->successResponse($this->storeService->all());
    }

    public function store(Request $request)
    {
        return $this->errorResponse('Danh mục kho HIMOTO cố định 6 kho; không tạo thêm kho mới.', 422);
    }

    public function update(Request $request, Store $store)
    {
        if (!HimotoStores::isCanonical($store)) {
            return $this->errorResponse('Không thể sửa kho ngoài danh mục 6 kho HIMOTO.', 422);
        }
        $data = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_phone' => 'nullable|string|max:30',
            'store_address' => 'nullable|string|max:255',
        ]);
        $store->update($data);
        return $this->successResponse($store->fresh(), 'Đã cập nhật cửa hàng.');
    }

    public function show(Store $store)
    {
        if (!HimotoStores::isCanonical($store)) {
            return $this->errorResponse('Kho không còn nằm trong danh mục 6 kho HIMOTO.', 404);
        }
        return $this->successResponse($store, 'Lấy dữ liệu thành công');
    }

	public function destroy(Store $store): \Illuminate\Http\JsonResponse
    {
        return $this->errorResponse('Không thể xóa kho chuẩn. Chỉ migration quản trị danh mục mới được xử lý kho thừa.', 422);
    }
}
