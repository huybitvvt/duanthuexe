<?php

namespace App\Http\Middleware;

use App\Support\PermissionAccess;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Yêu cầu đăng nhập để tiếp tục.',
                'error' => 'Unauthorized'
            ], 401);
        }

        $storeId = $request->input('store_id') ?: $request->route('store_id');
        $storeIdInt = $storeId ? (int) $storeId : null;

        if (!PermissionAccess::allows($user, $permission, $storeIdInt)) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này hoặc không thuộc cơ sở được phân công.',
                'error' => 'Forbidden',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
