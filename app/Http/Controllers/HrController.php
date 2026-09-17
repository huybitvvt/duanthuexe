<?php

namespace App\Http\Controllers;

use App\Http\Services\HrService;
use App\Http\Services\AuditService;
use App\Models\StaffAttendance;
use App\Models\StoreDutySchedule;
use App\Models\StaffProfile;
use App\Support\PermissionAccess;
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
        $requestedStoreId = $request->filled('store_id') ? (int) $request->input('store_id') : null;
        PermissionAccess::can($user, 'hr.view', $requestedStoreId);

        // Store-scoped roles must never broaden a missing filter into a
        // company-wide HR query. Company HR/BGĐ accounts have no store_id.
        if (!PermissionAccess::isAdmin($user) && $user->store_id) {
            $filters['store_id'] = (int) $user->store_id;
        }

        // Only HR managers/admins may receive the full identity number.
        $maskSensitive = !PermissionAccess::allows($user, 'hr.manage_staff', $requestedStoreId);
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

        $existing = $staffId ? StaffProfile::findOrFail((int) $staffId) : null;
        $targetStoreId = isset($validated['store_id'])
            ? (int) $validated['store_id']
            : ($existing && $existing->store_id ? (int) $existing->store_id : null);
        PermissionAccess::can($user, 'hr.manage_staff', $targetStoreId);

        try {
            $before = $existing ? $existing->toArray() : null;
            $staff = $this->hrService->saveStaffProfile($validated, $staffId);
            AuditService::log(
                $existing ? 'hr.staff.update' : 'hr.staff.create',
                $staff,
                $before,
                $staff->toArray(),
                $existing ? 'Cập nhật hồ sơ nhân sự' : 'Tạo hồ sơ nhân sự',
                $staff->store_id,
                $user->id
            );
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
        PermissionAccess::can($user, 'hr.view', $requestedStoreId);
        if (!PermissionAccess::isAdmin($user) && $user->store_id) {
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
        PermissionAccess::can($user, 'hr.view', $storeId);

        if (!PermissionAccess::isAdmin($user) && $user->store_id) {
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
            PermissionAccess::can($user, 'hr.manage_attendance', $staff->store_id ? (int) $staff->store_id : null);

            $beforeModel = StaffAttendance::where('staff_id', $staff->id)
                ->where('attendance_date', $validated['attendance_date'])
                ->first();
            $before = $beforeModel ? $beforeModel->toArray() : null;

            $attendance = $this->hrService->saveAttendance($validated, $user->id);
            AuditService::log(
                $beforeModel ? 'hr.attendance.update' : 'hr.attendance.create',
                $attendance,
                $before,
                $attendance->toArray(),
                'Lưu chấm công nhân sự',
                $staff->store_id,
                $user->id
            );
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
        $requestedStoreId = $requestedStoreId !== null ? (int) $requestedStoreId : null;
        PermissionAccess::can($user, 'hr.view', $requestedStoreId);

        if (!PermissionAccess::isAdmin($user) && $user->store_id) {
            $storeId = (int)$user->store_id;
        } else {
            $storeId = $requestedStoreId;
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
            PermissionAccess::can($user, 'hr.manage_schedule', (int) $validated['store_id']);
            $schedule = $this->hrService->saveStoreDutySchedule($validated, $user->id);
            AuditService::log(
                'hr.schedule.create',
                $schedule,
                null,
                $schedule->toArray(),
                'Tạo lịch trực cơ sở',
                $schedule->store_id,
                $user->id
            );
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
            PermissionAccess::can($user, 'hr.manage_schedule', (int) $schedule->store_id);
            $before = $schedule->toArray();

            $this->hrService->deleteStoreDutySchedule($id, $user->id);
            AuditService::log(
                'hr.schedule.delete',
                $schedule,
                $before,
                null,
                'Xóa lịch trực cơ sở',
                $schedule->store_id,
                $user->id
            );
            return $this->successResponse(null, 'Đã xoá ca trực thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
