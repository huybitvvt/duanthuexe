<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SeedRbacPermissionsAndRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = Carbon::now();

        // 1. Standard Permissions defined in plan
        $permissions = [
            // KPI
            ['slug' => 'kpi.view_company', 'name' => 'Xem KPI Toàn Công Ty'],
            ['slug' => 'kpi.view_store', 'name' => 'Xem KPI Cơ Sở'],
            ['slug' => 'kpi.export', 'name' => 'Xuất Dữ Liệu KPI'],

            // Accounting
            ['slug' => 'accounting.view', 'name' => 'Xem Sổ Kế Toán'],
            ['slug' => 'accounting.post', 'name' => 'Hạch Toán Chứng Từ'],
            ['slug' => 'accounting.reverse', 'name' => 'Đảo Bút Toán Kế Toán'],
            ['slug' => 'accounting.close_period', 'name' => 'Khóa & Mở Kỳ Kế Toán'],
            ['slug' => 'accounting.reconcile', 'name' => 'Đối Soát Sổ Cái'],
            ['slug' => 'accounting.export', 'name' => 'Xuất Báo Cáo Kế Toán'],

            // HR
            ['slug' => 'hr.view', 'name' => 'Xem Thông Tin Nhân Sự'],
            ['slug' => 'hr.manage_staff', 'name' => 'Quản Lý Hồ Sơ Nhân Viên'],
            ['slug' => 'hr.manage_attendance', 'name' => 'Quản Lý Chấm Công'],
            ['slug' => 'hr.manage_schedule', 'name' => 'Phân Ca Trực Cơ Sở'],
            ['slug' => 'hr.export', 'name' => 'Xuất Dữ Liệu Nhân Sự'],

            // Lease-to-own
            ['slug' => 'lease.view', 'name' => 'Xem Hợp Đồng Thuê Sở Hữu'],
            ['slug' => 'lease.collect', 'name' => 'Thu Tiền Kỳ Thuê Sở Hữu'],
            ['slug' => 'lease.reverse_payment', 'name' => 'Đảo Thu Hợp Đồng Thuê'],
            ['slug' => 'lease.ownership_request', 'name' => 'Tạo Yêu Cầu Chuyển Quyền'],
            ['slug' => 'lease.ownership_approve', 'name' => 'Phê Duyệt Chuyển Quyền'],
            ['slug' => 'lease.ownership_execute', 'name' => 'Thực Thi Chuyển Quyền'],
            ['slug' => 'lease.export', 'name' => 'Xuất Dữ Liệu Thuê Sở Hữu'],

            // Reminders
            ['slug' => 'reminder.view', 'name' => 'Xem Danh Sách Nhắc Nợ'],
            ['slug' => 'reminder.manage', 'name' => 'Quản Lý Cấu Hình Nhắc Nợ'],
            ['slug' => 'reminder.dispatch_live', 'name' => 'Phát Lệnh Gửi Tin Thật'],

            // GPS
            ['slug' => 'gps.view', 'name' => 'Xem Vị Trí Xe Hạm Đội'],
            ['slug' => 'gps.manage_devices', 'name' => 'Quản Lý Thiết Bị GPS'],
            ['slug' => 'gps.manage_alerts', 'name' => 'Quản Lý Cảnh Báo Định Vị'],
            ['slug' => 'gps.recovery_action', 'name' => 'Thực Hiện Thu Hồi Xe'],
        ];

        foreach ($permissions as $p) {
            $exists = DB::table('permissions')->where('slug', $p['slug'])->first();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'slug' => $p['slug'],
                    'name' => $p['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2. Standard Roles
        $roles = [
            ['slug' => 'quan-tri-vien', 'name' => 'Quản Trị Viên'],
            ['slug' => 'ban-giam-doc', 'name' => 'Ban Giám Đốc'],
            ['slug' => 'ke-toan', 'name' => 'Kế Toán'],
            ['slug' => 'nhan-su', 'name' => 'Nhân Sự'],
            ['slug' => 'quan-ly-cua-hang', 'name' => 'Quản Lý Cửa Hàng'],
            ['slug' => 'nhan-vien', 'name' => 'Nhân Viên'],
        ];

        foreach ($roles as $r) {
            $exists = DB::table('roles')->where('slug', $r['slug'])->first();
            if (!$exists) {
                DB::table('roles')->insert([
                    'slug' => $r['slug'],
                    'name' => $r['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Map default permissions to roles if roles_permissions exists
        if (Schema::hasTable('roles_permissions')) {
            $allPerms = DB::table('permissions')->pluck('id', 'slug')->toArray();
            $roleMap = DB::table('roles')->pluck('id', 'slug')->toArray();

            $rolePermConfig = [
                'ban-giam-doc' => [
                    'kpi.view_company', 'kpi.view_store', 'kpi.export',
                    'accounting.view', 'accounting.export',
                    'hr.view', 'hr.export',
                    'lease.view', 'lease.export', 'lease.ownership_approve',
                    'reminder.view', 'gps.view'
                ],
                'ke-toan' => [
                    'accounting.view', 'accounting.post', 'accounting.reverse',
                    'accounting.close_period', 'accounting.reconcile', 'accounting.export',
                    'kpi.view_store',
                    'lease.view', 'lease.collect', 'lease.reverse_payment',
                    'reminder.view'
                ],
                'nhan-su' => [
                    'hr.view', 'hr.manage_staff', 'hr.manage_attendance', 'hr.manage_schedule', 'hr.export'
                ],
                'quan-ly-cua-hang' => [
                    'lease.view', 'lease.collect', 'lease.ownership_request',
                    'kpi.view_store', 'gps.view', 'reminder.view', 'hr.view'
                ],
                'nhan-vien' => [
                    'lease.view', 'lease.collect', 'lease.ownership_request',
                    'reminder.view'
                ]
            ];

            foreach ($rolePermConfig as $roleSlug => $permSlugs) {
                if (!isset($roleMap[$roleSlug])) {
                    continue;
                }
                $roleId = $roleMap[$roleSlug];
                foreach ($permSlugs as $pSlug) {
                    if (isset($allPerms[$pSlug])) {
                        $pId = $allPerms[$pSlug];
                        $linkExists = DB::table('roles_permissions')
                            ->where('role_id', $roleId)
                            ->where('permission_id', $pId)
                            ->exists();
                        if (!$linkExists) {
                            DB::table('roles_permissions')->insert([
                                'role_id' => $roleId,
                                'permission_id' => $pId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Safe reversible migration: No drops of pre-existing core tables
    }
}
