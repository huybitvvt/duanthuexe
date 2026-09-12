<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Services\StoreService;
use App\Models\Store;
use App\Repositories\StoreRepository;
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
        $this->validate($request, [
            'store_name' => 'required|unique:stores,store_name',
            'store_phone' => 'required|unique:stores,store_phone',
        ], [
            'store_name.unique' => 'Tên cửa hàng đã tồn tại',
            'store_phone.unique' => 'Số điện thoại đã tồn tại'
        ]);
        $result = $this->storeService->store($request->all());
        return $this->successResponse($result, 'Tạo mới thành công');
    }

    public function update(Request $request, Store $store)
    {
        $this->validate($request, [
            'store_name' => 'required|unique:stores,store_name,' . $store->id,
            'store_phone' => 'required|unique:stores,store_phone,' . $store->id,
        ], [
            'store_name.required' => 'Tên cửa hàng không được để trống',
            'store_name.unique' => 'Tên cửa hàng đã tồn tại',
            'store_phone.required' => 'Số điện thoại không được để trống',
            'store_phone.unique' => 'Số điện thoại đã tồn tại'
        ]);
        $result = $this->storeService->update($store->id, $request->all());
        return $this->successResponse($result, 'Sửa thành công');
    }

    public function show(Store $store)
    {
        return $this->successResponse($store, 'Lấy dữ liệu thành công');
    }

	public function destroy(Store $store): \Illuminate\Http\JsonResponse
    {
        $deleted = $store->update(['status' => 'closing']);
        // $deleted = $store->delete();
        if ($deleted) {
            return $this->successResponse(true, 'Store đã được xóa thành công');
        } else {
            return $this->errorResponse('Xảy ra lỗi khi xóa store', 500);
        }
    }
}
