<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Services\VehicleService;
use App\Models\Vehicle;
use App\Models\VehicleImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    private $vehicleService;

    /**
     * @param VehicleService $vehicleService
     */
    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $is_all = $request->get('is_all', false);
        return $this->successResponse($this->vehicleService->index($request->all(), $is_all));
    }

    public function indexWithRevenue(Request $request)
    {
        $is_all = $request->get('is_all', false);
        return $this->successResponse($this->vehicleService->indexWithRevenue($request->all(), $is_all));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function report(Request $request)
    {
        return $this->successResponse($this->vehicleService->reportByParams($request->all()));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'brand' => 'required',
            'type' => 'required',
            'year' => 'required',
            'store_id' => 'required',
            'license' => 'required',
            'chassis' => 'required',
            'engine' => 'required',
            'status' => 'required',
            'cost_price' => 'required|integer',
            'price_range' => 'required|integer',
            'price_min' => 'nullable|integer',
            'price_max' => 'nullable|integer',
            'type_of_service_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu xe không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $request->merge(['created_by' => auth()->id()]);
        $vehicle = $this->vehicleService->store($request->all());

		$image_ids = $request->get('image_ids');
		if ($vehicle && $vehicle->id && is_array( $image_ids ) && ! empty( $image_ids ) ) {
			foreach ( $image_ids as $image_id ) {
				$vehicle_image = new VehicleImages();
				$vehicle_image->vehicle_id = $vehicle->id;
				$vehicle_image->file_id = $image_id;
				$vehicle_image->save();
			}
		}

        if (!$vehicle) {
            return $this->errorResponse('', Response::HTTP_BAD_REQUEST);
        }

        return $this->successResponse($vehicle);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'brand' => 'required',
            'type' => 'required',
            'year' => 'required',
            'store_id' => 'required',
            'license' => 'required',
            'chassis' => 'required',
            'engine' => 'required',
            'status' => 'required',
            'cost_price' => 'required|integer',
            'price_range' => 'required|integer',
            'price_min' => 'nullable|integer',
            'price_max' => 'nullable|integer',
            'type_of_service_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu xe không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $vehicle = $this->vehicleService->update($request->id, $request->all());

		$image_ids = $request->get('image_ids');
		if ( is_array( $image_ids ) ) {
			VehicleImages::where('vehicle_id', $request->id)->delete(); // Delete old records
			if ( ! empty( $image_ids ) ) {
				foreach ( $image_ids as $image_id ) {
					$vehicle_image = new VehicleImages();
					$vehicle_image->vehicle_id = $request->id;
					$vehicle_image->file_id = $image_id;
					$vehicle_image->save();
				}
			}
		}

        if (!$vehicle) {
            return $this->errorResponse('', Response::HTTP_BAD_REQUEST);
        }

        return $this->successResponse($vehicle);
    }

    /**
     * @param Vehicle $vehicle
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();
        return $this->successResponse('', 'Xóa thành công');
    }
}
