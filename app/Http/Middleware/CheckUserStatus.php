<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->status != 'active') {       
            Auth::logout();
            return response()->json([
                'error' => true,
                'message' => 'Your status is not active anymore.'
            ], 403);
        }
        return $next($request);
    }
}
