<?php

namespace App\Http\Services;

use App\Models\Department;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\Store;
use App\Models\StoreDutySchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrService
{
    /**
     * Lấy danh sách nhân sự (Hỗ trợ che thông tin nhạy cảm như CCCD nếu không có quyền Admin/HR).
     */
    public function getStaffList(array $filters = [], bool $maskSensitive = true)
    {
        $query = StaffProfile::with(['department', 'store', 'user:id,name,phone,email']);

        if (!empty($filters['department_id'])) {
            $query->where('department_id', (int)$filters['department_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', (int)$filters['store_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $keyword = $filters['keyword'] ?? ($filters['search'] ?? null);
        if (!empty($keyword)) {
            $kw = trim($keyword);
            $query->where(function ($q) use ($kw) {
                $q->where('full_name', 'LIKE', "%{$kw}%")
                    ->orWhere('phone', 'LIKE', "%{$kw}%")
                    ->orWhere('staff_code', 'LIKE', "%{$kw}%")
                    ->orWhere('email', 'LIKE', "%{$kw}%")
                    ->orWhere('position', 'LIKE', "%{$kw}%");
            });
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $paginator = $query->orderBy('full_name', 'asc')->paginate($perPage);

        if ($maskSensitive) {
            $paginator->getCollection()->transform(function ($staff) {
                if ($staff->id_card) {
                    $len = strlen($staff->id_card);
                    $staff->id_card = $len > 4 ? str_repeat('*', $len - 4) . substr($staff->id_card, -4) : '****';
                }
                return $staff;
            });
        }

        return $paginator;
    }

    /**
     * Sơ đồ tổ chức chỉ trả các trường liên hệ cần thiết, không trả CCCD.
     */
    public function getOrganizationChart(?int $storeId = null): array
    {
        $staff = StaffProfile::query()
            ->with('store:id,store_name')
            ->select([
                'id', 'staff_code', 'full_name', 'phone', 'email', 'position',
                'department_id', 'store_id', 'status',
            ])
            ->when($storeId, function ($query) use ($storeId) {
                return $query->where('store_id', $storeId);
            })
            ->where(function ($query) {
                $query->whereNull('status')->orWhereNotIn('status', ['resigned', 'terminated']);
            })
            ->orderBy('full_name')
            ->get();

        $staffById = $staff->keyBy('id');
        $departments = Department::query()->orderBy('name')->get();
        $units = [];

        foreach ($departments as $department) {
            $members = $staff->where('department_id', $department->id)->values();
            if ($storeId !== null && $members->isEmpty()) {
                continue;
            }

            $units[] = [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'description' => $department->description,
                'manager' => $department->manager_id ? $staffById->get($department->manager_id) : null,
                'staff_count' => $members->count(),
                'members' => $members,
            ];
        }

        $unassigned = $staff->filter(function ($profile) {
            return $profile->department_id === null;
        })->values();
        if ($unassigned->isNotEmpty()) {
            $units[] = [
                'id' => null,
                'code' => 'CHUA_PHAN_BO',
                'name' => 'Chưa phân phòng ban',
                'description' => 'Nhân sự cần được HCNS cập nhật phòng ban.',
                'manager' => null,
                'staff_count' => $unassigned->count(),
                'members' => $unassigned,
            ];
        }

        return $units;
    }

    /**
     * Danh sách chấm công theo ngày, bao gồm cả nhân sự chưa có bản ghi.
     */
    public function getAttendanceRows(string $date, ?int $storeId = null): array
    {
        $attendanceDate = Carbon::parse($date)->format('Y-m-d');
        $staff = StaffProfile::query()
            ->with([
                'store:id,store_name',
                'attendances' => function ($query) use ($attendanceDate) {
                    $query->where('attendance_date', $attendanceDate);
                },
            ])
            ->select(['id', 'staff_code', 'full_name', 'phone', 'position', 'store_id', 'status'])
            ->when($storeId, function ($query) use ($storeId) {
                return $query->where('store_id', $storeId);
            })
            ->where(function ($query) {
                $query->whereNull('status')->orWhereNotIn('status', ['resigned', 'terminated']);
            })
            ->orderBy('full_name')
            ->get();

        return $staff->map(function ($profile) use ($attendanceDate) {
            $attendance = $profile->attendances->first();

            return [
                'staff_id' => $profile->id,
                'staff_code' => $profile->staff_code,
                'full_name' => $profile->full_name,
                'phone' => $profile->phone,
                'position' => $profile->position,
                'store_id' => $profile->store_id,
                'store' => $profile->store,
                'attendance_date' => $attendanceDate,
                'attendance_id' => $attendance ? $attendance->id : null,
                'clock_in' => $attendance && $attendance->clock_in_at
                    ? Carbon::parse($attendance->clock_in_at)->format('H:i')
                    : null,
                'clock_out' => $attendance && $attendance->clock_out_at
                    ? Carbon::parse($attendance->clock_out_at)->format('H:i')
                    : null,
                'work_minutes' => $attendance ? (int) $attendance->work_minutes : 0,
                'attendance_status' => $attendance ? $attendance->status : 'not_recorded',
                'notes' => $attendance ? $attendance->notes : null,
            ];
        })->all();
    }

    /**
     * Tạo/cập nhật duy nhất một bản ghi chấm công cho mỗi nhân sự mỗi ngày.
     */
    public function saveAttendance(array $data, int $userId): StaffAttendance
    {
        $staff = StaffProfile::findOrFail((int) $data['staff_id']);
        $date = Carbon::parse($data['attendance_date'])->format('Y-m-d');
        $status = $data['status'] ?? 'present';
        $clockIn = $this->attendanceDateTime($date, $data['clock_in'] ?? null);
        $clockOut = $this->attendanceDateTime($date, $data['clock_out'] ?? null);

        if ($clockOut && !$clockIn) {
            throw ValidationException::withMessages([
                'clock_in' => 'Phải nhập giờ vào trước khi nhập giờ ra.',
            ]);
        }
        if ($clockIn && $clockOut && $clockOut->lessThan($clockIn)) {
            throw ValidationException::withMessages([
                'clock_out' => 'Giờ ra phải sau giờ vào trong cùng ngày.',
            ]);
        }

        if (in_array($status, ['absent', 'leave'], true)) {
            $clockIn = null;
            $clockOut = null;
        }

        $workMinutes = ($clockIn && $clockOut) ? $clockIn->diffInMinutes($clockOut) : 0;

        return DB::transaction(function () use ($data, $staff, $date, $status, $clockIn, $clockOut, $workMinutes, $userId) {
            return StaffAttendance::updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'attendance_date' => $date,
                ],
                [
                    'store_id' => $staff->store_id,
                    'clock_in_at' => $clockIn ? $clockIn->toDateTimeString() : null,
                    'clock_out_at' => $clockOut ? $clockOut->toDateTimeString() : null,
                    'work_minutes' => $workMinutes,
                    'status' => $status,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $userId,
                ]
            );
        });
    }

    private function attendanceDateTime(string $date, ?string $time): ?Carbon
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . trim($time), 'Asia/Ho_Chi_Minh');
    }

    /**
     * Tạo hoặc cập nhật hồ sơ nhân sự với allowlist an toàn.
     */
    public function saveStaffProfile(array $data, ?int $id = null): StaffProfile
    {
        if (empty($data['full_name']) || empty($data['phone'])) {
            throw ValidationException::withMessages(['full_name' => 'Họ tên và số điện thoại là bắt buộc.']);
        }

        $allowlist = [
            'staff_code', 'full_name', 'phone', 'email', 'id_card', 'position',
            'department_id', 'store_id', 'status', 'joined_at', 'notes'
        ];
        $filtered = array_intersect_key($data, array_flip($allowlist));

        if ($id) {
            $staff = StaffProfile::findOrFail($id);
            $staff->update($filtered);
        } else {
            if (empty($filtered['staff_code'])) {
                $maxId = (int) StaffProfile::max('id') + 1;
                $filtered['staff_code'] = sprintf('NV%04d', $maxId);
            }
            $staff = StaffProfile::create($filtered);
        }

        return $staff;
    }

    /**
     * Lịch trực cửa hàng theo ngày.
     */
    public function getStoreDutySchedules(string $date, ?int $storeId = null): array
    {
        $parsedDate = Carbon::parse($date)->format('Y-m-d');

        $stores = Store::when($storeId, function ($q) use ($storeId) {
            return $q->where('id', $storeId);
        })->get();

        $schedules = StoreDutySchedule::with([
            'store',
            'staff' => function ($q) {
                $q->select('id', 'staff_code', 'full_name', 'phone', 'position', 'store_id');
            }
        ])
            ->where('duty_date', $parsedDate)
            ->when($storeId, function ($q) use ($storeId) {
                return $q->where('store_id', $storeId);
            })
            ->get();

        $result = [];
        foreach ($stores as $store) {
            $storeSchedules = $schedules->where('store_id', $store->id)->values();
            $result[] = [
                'store_id' => $store->id,
                'store_name' => $store->store_name,
                'store_address' => $store->store_address,
                'store_phone' => $store->store_phone,
                'duty_date' => $parsedDate,
                'schedules' => $storeSchedules,
            ];
        }

        return $result;
    }

    /**
     * Thêm phân ca trực cửa hàng.
     */
    public function saveStoreDutySchedule(array $data, int $userId): StoreDutySchedule
    {
        $storeId = data_get($data, 'store_id');
        $dutyDate = data_get($data, 'duty_date');
        $staffName = data_get($data, 'staff_name');
        $staffPhone = data_get($data, 'staff_phone');
        $shiftName = data_get($data, 'shift_name', 'Cả ngày');
        $roleInShift = data_get($data, 'role_in_shift', 'Nhân viên trực');

        if (!$storeId || !$dutyDate || !$staffName) {
            throw ValidationException::withMessages([
                'store_id' => 'Cơ sở, ngày trực và tên nhân viên là bắt buộc.'
            ]);
        }

        $dutyDateStr = Carbon::parse($dutyDate)->format('Y-m-d');

        $schedule = StoreDutySchedule::create([
            'store_id' => (int) $storeId,
            'duty_date' => $dutyDateStr,
            'shift_name' => $shiftName,
            'staff_id' => data_get($data, 'staff_id'),
            'staff_name' => $staffName,
            'staff_phone' => $staffPhone,
            'role_in_shift' => $roleInShift,
            'notes' => data_get($data, 'notes'),
            'created_by' => $userId,
        ]);

        return $schedule;
    }

    /**
     * Xoá ca trực.
     */
    public function deleteStoreDutySchedule(int $id, int $userId): bool
    {
        $schedule = StoreDutySchedule::findOrFail($id);
        return $schedule->delete();
    }
}
