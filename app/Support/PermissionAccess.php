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
    private static $roleCapabilities = [
        'quan-tri-vien' => ['*'],
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

        // 1. Check database pivot if available
        if (Schema::hasTable('roles_permissions') && Schema::hasTable('permissions') && $user->role_id) {
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
        if (!$hasPermission && $roleSlug && isset(self::$roleCapabilities[$roleSlug])) {
            $caps = self::$roleCapabilities[$roleSlug];
            $hasPermission = in_array('*', $caps, true) || in_array($permission, $caps, true);
        }

        if (!$hasPermission) {
            return false;
        }

        // Check store scope
        if ($storeId !== null) {
            // Company-wide roles do not get blocked by store scope
            if (in_array($roleSlug, ['ban-giam-doc', 'quan-tri-vien'], true) || $permission === 'kpi.view_company') {
                return true;
            }

            if ($user->store_id && (int) $user->store_id !== (int) $storeId) {
                return false;
            }
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

        // DB Permissions
        if (Schema::hasTable('roles_permissions') && Schema::hasTable('permissions') && $user->role_id) {
            $caps = DB::table('roles_permissions')
                ->join('permissions', 'roles_permissions.permission_id', '=', 'permissions.id')
                ->where('roles_permissions.role_id', $user->role_id)
                ->pluck('permissions.slug')
                ->toArray();
        }

        // Fallback map
        if (empty($caps) && $roleSlug && isset(self::$roleCapabilities[$roleSlug])) {
            $caps = self::$roleCapabilities[$roleSlug];
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
