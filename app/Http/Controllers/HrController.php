<?php

namespace App\Http\Controllers;

use App\Http\Services\HrService;
use App\Models\StoreDutySchedule;
use App\Support\PilotAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrController extends Controller
{
    protected $hrService;

    public function __construct(HrService $hrService)
    {
        $this->hrService = $hrService;
    }

    /**
     * Danh sách nhân sự.
     */
    public function staffIndex(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $filters = $request->all();
        $isAdmin = PilotAccess::isAdmin($user);

        // Nhân viên chỉ được xem danh sách nhân sự tại cơ sở của mình
        if (!$isAdmin) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($request->filled('store_id') && (int)$request->input('store_id') !== (int)$user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem nhân sự của cơ sở khác.', 403);
            }
            $filters['store_id'] = (int) $user->store_id;
        }

        // Che số CCCD/id_card nếu người xem không phải Admin
        $maskSensitive = !$isAdmin;
        $staff = $this->hrService->getStaffList($filters, $maskSensitive);

        return $this->successResponse($staff);
    }

    /**
     * Lưu hồ sơ nhân sự (Admin hoặc Quản lý nhân sự).
     */
    public function staffStore(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        if (!PilotAccess::isAdmin($user)) {
            return $this->errorResponse('Chỉ Admin mới có quyền tạo hoặc chỉnh sửa hồ sơ nhân sự.', 403);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'id_card' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:100',
            'store_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'status' => 'nullable|string|max:30',
            'joined_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $staff = $this->hrService->saveStaffProfile($validated, $request->input('id'));
            return $this->successResponse($staff, 'Lưu thông tin nhân sự thành công.');
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
     * Lấy lịch trực cửa hàng theo ngày.
     */
    public function dutySchedules(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $date = $request->input('date', date('Y-m-d'));
        $requestedStoreId = $request->input('store_id');

        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($requestedStoreId !== null && (int)$requestedStoreId !== (int)$user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem lịch trực của cơ sở khác.', 403);
            }
            $storeId = (int)$user->store_id;
        } else {
            $storeId = $requestedStoreId ? (int)$requestedStoreId : null;
        }

        $schedules = $this->hrService->getStoreDutySchedules($date, $storeId);
        return $this->successResponse($schedules);
    }

    /**
     * Thêm ca trực cửa hàng.
     */
    public function saveDutySchedule(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $validated = $request->validate([
            'store_id' => 'required|integer',
            'duty_date' => 'required|date_format:Y-m-d',
            'staff_name' => 'required|string|max:150',
            'staff_phone' => 'nullable|string|max:30',
            'shift_name' => 'nullable|string|max:50',
            'role_in_shift' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'staff_id' => 'nullable|integer|exists:staff_profiles,id',
        ]);

        try {
            PilotAccess::store($user, $validated['store_id']);
            $schedule = $this->hrService->saveStoreDutySchedule($validated, $user->id);
            return $this->successResponse($schedule, 'Đã lưu lịch trực cửa hàng thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
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
     * Xoá ca trực.
     */
    public function deleteDutySchedule(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        try {
            $schedule = StoreDutySchedule::findOrFail($id);
            PilotAccess::store($user, $schedule->store_id);

            $this->hrService->deleteStoreDutySchedule($id, $user->id);
            return $this->successResponse(null, 'Đã xoá ca trực thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
