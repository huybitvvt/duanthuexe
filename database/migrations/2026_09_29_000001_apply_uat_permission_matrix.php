<?php

use App\Support\UatPermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplyUatPermissionMatrix extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('roles_permissions')) {
            throw new RuntimeException('RBAC tables are required before applying the UAT permission matrix.');
        }

        DB::transaction(function () {
            $now = now();
            $permissionNames = UatPermissionMatrix::PERMISSIONS;
            foreach (UatPermissionMatrix::ROLES as $role) {
                foreach ($role[1] as $permissionSlug) {
                    if (!isset($permissionNames[$permissionSlug])) {
                        $permissionNames[$permissionSlug] = $permissionSlug;
                    }
                }
            }
            foreach ($permissionNames as $slug => $name) {
                if (!DB::table('permissions')->where('slug', $slug)->exists()) {
                    DB::table('permissions')->insert([
                        'slug' => $slug, 'name' => $name,
                        'updated_at' => $now, 'created_at' => $now,
                    ]);
                }
            }

            foreach (UatPermissionMatrix::ROLES as $slug => [$name, $permissionSlugs]) {
                if (!DB::table('roles')->where('slug', $slug)->exists()) {
                    DB::table('roles')->insert([
                        'slug' => $slug, 'name' => $name,
                        'updated_at' => $now, 'created_at' => $now,
                    ]);
                }
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                $permissionIds = [];
                foreach ($permissionSlugs as $permissionSlug) {
                    $permissionId = DB::table('permissions')->where('slug', $permissionSlug)->value('id');
                    if (!$permissionId) {
                        throw new RuntimeException('Missing permission: '.$permissionSlug);
                    }
                    $permissionIds[] = $permissionId;
                    $link = DB::table('roles_permissions')->where('role_id', $roleId)
                        ->where('permission_id', $permissionId);
                    if (!$link->exists()) {
                        DB::table('roles_permissions')->insert([
                            'role_id' => $roleId, 'permission_id' => $permissionId,
                            'updated_at' => $now, 'created_at' => $now,
                        ]);
                    }
                }
                // A role's omitted capability is a denial, including legacy
                // lease and accounting grants inherited by branch staff.
                DB::table('roles_permissions')->where('role_id', $roleId)
                    ->whereNotIn('permission_id', $permissionIds)->delete();
            }
        });
    }

    public function down()
    {
        // Historical grants cannot be reconstructed safely from this migration.
    }
}
