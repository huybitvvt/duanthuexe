<?php

namespace App\Http\Middleware;

use App\Entities\Role;
use App\Models\UserRole;
use Closure;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        if ($user->role_id !== 1) {
            return response()->json([
                'error' => true,
                'message' => 'Bạn không có quyền, vui lòng liên hệ admin'
            ], 403);
        }
        return $next($request);
    }
}
