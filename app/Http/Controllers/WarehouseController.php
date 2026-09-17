<?php

namespace App\Http\Controllers;

use App\Http\Services\WarehouseService;
use App\Http\Services\VehicleTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    protected $warehouseService;
    protected $transferService;

    public function __construct(WarehouseService $warehouseService, VehicleTransferService $transferService)
    {
        $this->warehouseService = $warehouseService;
        $this->transferService = $transferService;
    }

    /**
     * Get aggregate summary cards for all warehouses / stores.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $summary = $this->warehouseService->getSummary($user);
        return $this->successResponse($summary);
    }

    /**
     * Get vehicle list for a specific warehouse with role permissions.
     */
    public function vehicles(Request $request, int $storeId): JsonResponse
    {
        $user = Auth::user();
        try {
            $data = $this->warehouseService->getStoreVehicles($storeId, $request->all(), $user);
            return $this->successResponse($data);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function transfers(Request $request): JsonResponse
    {
        try {
            return $this->successResponse($this->warehouseService->getTransfers($request->all(), Auth::user()));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function lookupReturnByLicense(Request $request): JsonResponse
    {
        $request->validate(['license' => 'required|string|max:30']);
        try {
            return $this->successResponse($this->warehouseService->lookupReturnByLicense($request->input('license'), Auth::user()));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        }
    }

    /**
     * Dispatch vehicles from one store to another (Flow A).
     */
    public function dispatchTransfer(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'from_store_id' => 'required|integer',
            'to_store_id' => 'required|integer',
            'vehicle_ids' => 'required|array|min:1',
            'vehicle_ids.*' => 'integer',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        try {
            $transfer = $this->transferService->dispatchStoreTransfer($request->all(), $user);
            return $this->successResponse($transfer, 'Đã xuất kho điều chuyển thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Confirm receiving transferred vehicles at destination store (Flow A).
     */
    public function receiveTransfer(Request $request, int $transferId): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'odometers' => 'nullable|array',
            'condition_notes' => 'nullable|array',
        ]);

        try {
            $transfer = $this->transferService->receiveStoreTransfer($transferId, $request->all(), $user);
            return $this->successResponse($transfer, 'Đã xác nhận nhập kho thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Cancel an in-transit transfer (Flow A).
     */
    public function cancelTransfer(Request $request, int $transferId): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $transfer = $this->transferService->cancelStoreTransfer($transferId, $request->input('reason'), $user);
            return $this->successResponse($transfer, 'Đã hủy điều chuyển, xe đã hoàn kho.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Customer returns vehicle at a different branch store (Flow B).
     */
    public function returnDifferentStore(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'order_id' => 'required|integer',
            'vehicle_id' => 'nullable|integer',
            'return_store_id' => 'required|integer',
            'odometer' => 'nullable|integer',
            'condition_notes' => 'nullable|string',
            'returned_at' => 'nullable|date',
        ]);

        try {
            $result = $this->transferService->processReturnDifferentStore(
                (int)$request->input('order_id'),
                (int)$request->input('return_store_id'),
                $request->all(),
                $user
            );
            return $this->successResponse($result, 'Đã ghi nhận nhận xe trả tại cơ sở khác thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Exchange broken/unsuitable vehicle for an active contract (Flow C).
     */
    public function exchangeVehicle(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'order_id' => 'required|integer',
            'old_vehicle_id' => 'required|integer',
            'new_vehicle_id' => 'required|integer',
            'reason' => 'required|string',
            'condition_notes' => 'nullable|string',
            'price_difference' => 'nullable|numeric',
            'payment_method' => 'nullable|in:1,2,TM,CK,cash,bank',
            'cash_id' => 'nullable|integer',
            'bank_id' => 'nullable|integer',
            'exchange_store_id' => 'nullable|integer',
            'effective_at' => 'nullable|date',
            'old_vehicle_odometer' => 'nullable|integer',
            'new_vehicle_odometer' => 'nullable|integer',
        ]);

        try {
            $result = $this->transferService->processVehicleExchange(
                (int)$request->input('order_id'),
                (int)$request->input('old_vehicle_id'),
                (int)$request->input('new_vehicle_id'),
                $request->all(),
                $user
            );
            return $this->successResponse($result, 'Đã đổi xe và cập nhật phụ lục hợp đồng thành công.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get complete movement history ledger for a vehicle.
     */
    public function movementHistory(int $vehicleId): JsonResponse
    {
        try {
            $user = Auth::user();
            $vehicle = \App\Models\Vehicle::findOrFail($vehicleId);
            $isOwnStore = $user && in_array((int)$user->store_id, [(int)$vehicle->store_id, (int)$vehicle->current_store_id]);
            if (!$isOwnStore) {
                \App\Support\PilotAccess::admin($user);
            }
            $history = $this->transferService->getVehicleMovementHistory($vehicleId);
            return $this->successResponse($history);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) { return $this->errorResponse($e->getMessage(), 403); }
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
