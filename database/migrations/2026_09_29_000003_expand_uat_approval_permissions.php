<?php

use App\Support\UatPermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandUatApprovalPermissions extends Migration
{
    public function up()
    {
        DB::transaction(function () {
            $now = now();
            $permissionNames = UatPermissionMatrix::PERMISSIONS;
            foreach (UatPermissionMatrix::ROLES as $role) {
                foreach ($role[1] as $slug) {
                    if (!isset($permissionNames[$slug])) {
                        $permissionNames[$slug] = $slug;
                    }
                }
            }
            foreach ($permissionNames as $slug => $name) {
                if (!DB::table('permissions')->where('slug', $slug)->exists()) {
                    DB::table('permissions')->insert([
                        'slug' => $slug, 'name' => $name,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            foreach (UatPermissionMatrix::ROLES as $slug => [$name, $grants]) {
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                if (!$roleId) {
                    throw new RuntimeException("UAT role missing: {$slug}");
                }
                $ids = DB::table('permissions')->whereIn('slug', $grants)->pluck('id')->all();
                if (count($ids) !== count($grants)) {
                    throw new RuntimeException("UAT permissions missing: {$slug}");
                }
                foreach ($ids as $id) {
                    if (!DB::table('roles_permissions')->where('role_id', $roleId)->where('permission_id', $id)->exists()) {
                        DB::table('roles_permissions')->insert([
                            'role_id' => $roleId, 'permission_id' => $id,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
                DB::table('roles_permissions')->where('role_id', $roleId)
                    ->whereNotIn('permission_id', $ids)->delete();
            }
        });
    }

    public function down()
    {
        // A previous role grant cannot be reconstructed without its backup.
    }
}
