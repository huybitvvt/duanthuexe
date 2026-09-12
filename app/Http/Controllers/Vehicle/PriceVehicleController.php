<?php


namespace App\Http\Controllers\Vehicle;


use App\Http\Controllers\Controller;
use App\Http\Services\PriceVehicleService;
use App\Models\PriceVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceVehicleController extends Controller
{
    protected $priceVehicleService;

    public function __construct(PriceVehicleService $priceVehicleService)
    {
        $this->priceVehicleService = $priceVehicleService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $priceVehicles = $this->priceVehicleService->index($request);
        return $this->successResponse($priceVehicles);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeOrUpdate(Request $request): JsonResponse
    {
        $this->priceVehicleService->storeOrUpdate($request);
        return $this->successResponse('', 'Cập nhật bảng giá thành công');
    }

    /**
     * @param PriceVehicle $priceVehicle
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy(PriceVehicle $priceVehicle): JsonResponse
    {
        $priceVehicle->delete();
        return $this->successResponse('', 'Xóa thành công');
    }
}
