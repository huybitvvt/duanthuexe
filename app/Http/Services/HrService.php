<?php

namespace App\Http\Services;

use App\Models\Department;
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

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('full_name', 'LIKE', "%{$kw}%")
                    ->orWhere('phone', 'LIKE', "%{$kw}%")
                    ->orWhere('staff_code', 'LIKE', "%{$kw}%")
                    ->orWhere('position', 'LIKE', "%{$kw}%");
            });
        }

        $paginator = $query->orderBy('full_name', 'asc')->paginate($filters['per_page'] ?? 20);

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
     * Tạo hoặc cập nhật hồ sơ nhân sự với allowlist an toàn.
     */
    public function saveStaffProfile(array $data, ?int $id = null): StaffProfile
    {
        if (empty($data['full_name']) || empty($data['phone'])) {
            throw ValidationException::withMessages(['full_name' => 'Họ tên và số điện thoại là bắt buộc.']);
        }

        $allowlist = [
            'full_name', 'phone', 'email', 'id_card', 'position',
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
