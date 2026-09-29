<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackLeaseOriginAndCreatePermission extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lease_contracts') && !Schema::hasColumn('lease_contracts', 'origin_store_id')) {
            Schema::table('lease_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('origin_store_id')->nullable()->index();
            });
        }

        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('roles_permissions')) {
            return;
        }
        $permission = DB::table('permissions')->where('slug', 'lease.create')->first();
        $permissionId = $permission ? $permission->id : DB::table('permissions')->insertGetId([
            'slug' => 'lease.create', 'name' => 'Tạo Hợp Đồng Thuê Sở Hữu',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['nhan-vien', 'quan-ly-cua-hang'] as $slug) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if ($roleId && !DB::table('roles_permissions')->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)->exists()) {
                DB::table('roles_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Existing records may depend on the origin link. Do not discard it.
    }
}
