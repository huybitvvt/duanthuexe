<?php

namespace App\Http\Services\Users;

use App\Interfaces\ICrud;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;


class UserService implements ICrud
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function store(array $data)
    {
        $data['password'] = $this->hashPassword($data['password']);
        return $this->userRepository->create($data);
    }

    /**
     * Create the org-chart accounts in one transaction and file each person
     * into the HR profile when that table exists.
     */
    public function storeMany(string $password, array $rows): int
    {
        $prepared = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || isset($seen[$email])) {
                throw ValidationException::withMessages([
                    'users' => 'Email bị trùng hoặc để trống ở dòng '.($index + 1).'.',
                ]);
            }
            $seen[$email] = true;
            $prepared[] = [
                'name' => trim((string) $row['name']),
                'email' => $email,
                'phone' => $row['phone'] ?? null,
                'role_id' => (int) $row['role_id'],
                'store_id' => !empty($row['store_id']) ? (int) $row['store_id'] : null,
                'staff_code' => $row['staff_code'] ?? null,
                'position' => $row['position'] ?? null,
                'department_code' => $row['department_code'] ?? null,
                'department_name' => $row['department_name'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }

        $emails = array_keys($seen);
        $placeholders = implode(', ', array_fill(0, count($emails), '?'));
        $existing = User::withTrashed()
            ->whereRaw('lower(email) in ('.$placeholders.')', $emails)
            ->pluck('email')
            ->all();
        if ($existing) {
            throw ValidationException::withMessages([
                'users' => 'Email đã có tài khoản: '.implode(', ', $existing).'.',
            ]);
        }

        $slugs = DB::table('roles')
            ->whereIn('id', array_values(array_unique(array_column($prepared, 'role_id'))))
            ->pluck('slug', 'id');

        return DB::transaction(function () use ($password, $prepared, $slugs) {
            $count = 0;
            foreach ($prepared as $row) {
                $payload = [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password,
                    'role_id' => $row['role_id'],
                    'store_id' => $row['store_id'],
                    'phone' => $row['phone'],
                    'status' => 'active',
                ];
                $slug = $slugs[$row['role_id']] ?? $slugs[(string) $row['role_id']] ?? null;
                if ($slug) {
                    $payload['role'] = $slug;
                }
                $user = $this->store($payload);
                $this->syncOrgStaffProfile($user, $row);
                $count++;
            }
            return $count;
        });
    }

    private function syncOrgStaffProfile(User $user, array $row): void
    {
        if (empty($row['staff_code']) || !Schema::hasTable('staff_profiles')) {
            return;
        }

        $departmentId = null;
        if (!empty($row['department_code']) && Schema::hasTable('departments')) {
            $department = Department::firstOrCreate(
                ['code' => $row['department_code']],
                [
                    'name' => $row['department_name'] ?: $row['department_code'],
                    'description' => 'Sơ đồ Ban giám đốc 10/09/2025',
                ]
            );
            $departmentId = $department->id;
        }

        StaffProfile::updateOrCreate(
            ['staff_code' => $row['staff_code']],
            [
                'user_id' => $user->id,
                'full_name' => $row['name'],
                'phone' => $row['phone'] ?: '',
                'email' => $row['email'],
                'position' => $row['position'],
                'department_id' => $departmentId,
                'store_id' => $row['store_id'],
                'status' => 'active',
                'notes' => $row['notes'] ?: 'Sơ đồ 10/09/2025.',
            ]
        );
    }

    public function index(array $params)
    {
        $limit = data_get($params, 'limit', config('app.paginate', 20));

        $items = $this->userRepository->with(['role_rel', 'store:id,store_name'])->filter($params)->orderBy('id', 'DESC');

		$user = auth()->user();
		if ($user->role_rel->slug !== 'quan-tri-vien') {
			$items->where('store_id', $user->store_id);

			if ($user->role_rel->slug !== 'quan-ly-cua-hang') {
				$items->where('id', $user->id);
			}
		}

        return $items->paginate($limit);
    }

    /**
     * @param $store_id
     * @return mixed
     */
    public function getByStore($store_id)
    {
        return $this->userRepository->with(['role_rel'])
            ->where('store_id', $store_id)
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'role_id']);
    }

    public function update($id, array $params)
    {   
        if (isset(  $params['password'])){
            $params['password'] = $this->hashPassword($params['password']);
        }
        return $this->userRepository->update($params, $id);
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }

    public function all()
    {
        // TODO: Implement all() method.
    }
    public function hashPassword($str){
        return Hash::make($str);
    }
}
