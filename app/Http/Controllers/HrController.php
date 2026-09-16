<?php

namespace App\Http\Controllers;

use App\Http\Services\HrService;
use App\Models\StoreDutySchedule;
use App\Models\StaffProfile;
use App\Support\PilotAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

        $staffId = $request->input('id');
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:staff_profiles,id',
            'staff_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('staff_profiles', 'staff_code')->ignore($staffId),
            ],
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'id_card' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:100',
            'store_id' => 'nullable|integer|exists:stores,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'status' => 'nullable|string|max:30',
            'joined_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $staff = $this->hrService->saveStaffProfile($validated, $staffId);
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
     * Sơ đồ phòng ban và danh sách liên hệ nội bộ.
     */
    public function organizationChart(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $requestedStoreId = $request->filled('store_id') ? (int) $request->input('store_id') : null;
        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($requestedStoreId !== null && $requestedStoreId !== (int) $user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem nhân sự của cơ sở khác.', 403);
            }
            $requestedStoreId = (int) $user->store_id;
        }

        return $this->successResponse($this->hrService->getOrganizationChart($requestedStoreId));
    }

    public function attendanceIndex(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'store_id' => 'nullable|integer',
        ]);
        $storeId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;

        if (!PilotAccess::isAdmin($user)) {
            if (!$user->store_id) {
                return $this->errorResponse('Tài khoản chưa được gán cơ sở.', 403);
            }
            if ($storeId !== null && $storeId !== (int) $user->store_id) {
                return $this->errorResponse('Bạn không có quyền xem chấm công của cơ sở khác.', 403);
            }
            $storeId = (int) $user->store_id;
        }

        $rows = $this->hrService->getAttendanceRows($validated['date'] ?? date('Y-m-d'), $storeId);
        return $this->successResponse($rows);
    }

    public function attendanceStore(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Chưa đăng nhập.', 401);
        }

        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:staff_profiles,id',
            'attendance_date' => 'required|date_format:Y-m-d',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,late,absent,leave',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $staff = StaffProfile::findOrFail((int) $validated['staff_id']);
            if (!$staff->store_id && !PilotAccess::isAdmin($user)) {
                return $this->errorResponse('Nhân sự chưa được gán cơ sở.', 422);
            }
            if ($staff->store_id) {
                PilotAccess::store($user, (int) $staff->store_id);
            } elseif (!PilotAccess::isAdmin($user)) {
                return $this->errorResponse('Bạn không có quyền cập nhật nhân sự này.', 403);
            }

            $attendance = $this->hrService->saveAttendance($validated, $user->id);
            return $this->successResponse($attendance, 'Đã lưu chấm công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
                'errors' => $e->errors(),
            ], 422);
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
            'store_id' => 'required|integer|exists:stores,id',
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
