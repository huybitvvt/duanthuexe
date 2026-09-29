<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class PilotAccess
{
    public static function isAdmin(?User $user): bool
    {
        return $user && (PermissionAccess::isAdmin($user)
            || in_array(PermissionAccess::getRoleSlug($user), ['ban-giam-doc', 'van-hanh'], true));
    }

    public static function store(?User $user, $storeId): void
    {
        if (!self::isAdmin($user) && (!$user || !$user->store_id || (int) $user->store_id !== (int) $storeId)) {
            throw new AuthorizationException('Bạn không có quyền thao tác dữ liệu của cơ sở này.');
        }
    }

    public static function admin(?User $user): void
    {
        if (!self::isAdmin($user)) {
            throw new AuthorizationException('Chức năng này chỉ dành cho quản trị viên.');
        }
    }
}
