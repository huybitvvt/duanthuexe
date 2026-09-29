<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionAccess
{
    /**
     * Standard role to permission fallback map.
     */
    private static function fallbackCapabilities(?string $roleSlug): array
    {
        if (!$roleSlug || !isset(UatPermissionMatrix::ROLES[$roleSlug])) {
            return [];
        }

        return UatPermissionMatrix::ROLES[$roleSlug][1];
    }

    /**
     * Check if user is an administrator.
     */
    public static function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ((int) $user->role_id === 1 || $user->role === 'admin' || (int) $user->is_admin === 1) {
            return true;
        }

        if ($user->relationLoaded('role_rel') && $user->role_rel && $user->role_rel->slug === 'quan-tri-vien') {
            return true;
        }

        return false;
    }

    /**
     * Explicit name for callers that need to distinguish the unrestricted
     * administrator from company-wide functional roles (for example BGĐ).
     * Keep this as an alias so authorization semantics stay centralized.
     */
    public static function isSuperAdmin(?User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Determine if a user has a specific permission, optionally scoped to a store.
     */
    public static function allows(?User $user, string $permission, ?int $storeId = null): bool
    {
        if (!$user) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        $roleSlug = self::getRoleSlug($user);

        // Check if role has permission
        $hasPermission = false;

        // 1. The database matrix is authoritative when installed. A missing
        // pivot row is an explicit denial; do not silently re-grant it from
        // the fallback map after an administrator revokes a permission.
        $hasDatabaseMatrix = Schema::hasTable('roles_permissions')
            && Schema::hasTable('permissions')
            && !empty($user->role_id);
        if ($hasDatabaseMatrix) {
            $hasPermission = DB::table('roles_permissions')
                ->join('permissions', 'roles_permissions.permission_id', '=', 'permissions.id')
                ->where('roles_permissions.role_id', $user->role_id)
                ->where(function ($q) use ($permission) {
                    $q->where('permissions.slug', $permission)
                      ->orWhere('permissions.slug', '*');
                })
                ->exists();
        }

        // 2. Fallback to capability map
        if (!$hasDatabaseMatrix && !$hasPermission) {
            $caps = self::fallbackCapabilities($roleSlug);
            $hasPermission = in_array('*', $caps, true) || in_array($permission, $caps, true);
        }

        if (!$hasPermission) {
            return false;
        }

        // Check store scope
        if ($storeId !== null) {
            // Company-wide roles do not get blocked by store scope
            if (in_array($roleSlug, ['ban-giam-doc', 'van-hanh', 'ke-toan', 'nhan-su', 'telesale'], true)
                || in_array($permission, ['vehicle.view_all', 'finance.bank.view_all', 'finance.cash.view_all',
                    'cash_register.view_all', 'kpi.view_company'], true)) {
                return true;
            }

            return $user->store_id && (int) $user->store_id === (int) $storeId;
        }

        return true;
    }

    /**
     * Authorize or throw 403 exception.
     *
     * @throws AuthorizationException
     */
    public static function can(?User $user, string $permission, ?int $storeId = null): void
    {
        if (!self::allows($user, $permission, $storeId)) {
            throw new AuthorizationException('Bạn không có quyền thực hiện chức năng này hoặc không thuộc cơ sở được phân công.');
        }
    }

    /**
     * Retrieve list of capabilities for the user (used for login payload and UI gates).
     */
    public static function capabilities(?User $user): array
    {
        if (!$user) {
            return [];
        }

        if (self::isAdmin($user)) {
            return ['*'];
        }

        $roleSlug = self::getRoleSlug($user);
        $caps = [];

        // DB permissions are authoritative once the matrix exists, including
        // the valid case where a role intentionally has zero capabilities.
        $hasDatabaseMatrix = Schema::hasTable('roles_permissions')
            && Schema::hasTable('permissions')
            && !empty($user->role_id);
        if ($hasDatabaseMatrix) {
            $caps = DB::table('roles_permissions')
                ->join('permissions', 'roles_permissions.permission_id', '=', 'permissions.id')
                ->where('roles_permissions.role_id', $user->role_id)
                ->pluck('permissions.slug')
                ->toArray();
        }

        // Fallback map
        if (!$hasDatabaseMatrix && empty($caps)) {
            $caps = self::fallbackCapabilities($roleSlug);
        }

        return array_values(array_unique($caps));
    }

    /**
     * Get role slug safely from user.
     */
    public static function getRoleSlug(User $user): ?string
    {
        if ($user->relationLoaded('role_rel') && $user->role_rel) {
            return $user->role_rel->slug;
        }

        if ($user->role_id && Schema::hasTable('roles')) {
            return DB::table('roles')->where('id', $user->role_id)->value('slug');
        }

        return $user->role ?: null;
    }
}
