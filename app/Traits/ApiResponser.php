<?php

namespace App\Traits;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Cookie;

trait ApiResponser
{
    protected function successResponse($data = '', $message = null, $code = 200): JsonResponse
    {
        if ($data instanceof AnonymousResourceCollection) {
            return response()->json([
                'data' => $data,
                'pagination' => [
                    'total' => $data->total(),
                    'count' => $data->count(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
                'error' => false,
                'message' => $message
            ], $code, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_INVALID_UTF8_IGNORE);
        }
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $data
        ], $code, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_INVALID_UTF8_IGNORE);
    }

    protected function errorResponse($message = null, $code = 200, $data = null)
    {
        return response()->json([
            'error' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function respondWithToken($token, $cookie = '')
    {
        return response()->json([
            'user' => auth()->user()->transform(),
            'error' => false,
            'token_type' => 'bearer',
            'token' => $token,
            'expires_in' => auth(auth()->getDefaultDriver())->factory()->getTTL() * 60
        ], 200)->withCookie($cookie);
    }

    protected function respondParentsWithToken($token, $cookie = '') {
        return response()->json([
            'user' => auth()->user()->transformParents(),
            'error' => false,
            'token_type' => 'bearer',
            'token' => $token,
            'expires_in' => auth(auth()->getDefaultDriver())->factory()->getTTL() * 60
        ], 200)->withCookie($cookie);
    }

    protected function getCookie($token, $expired = 0)
    {
        return Cookie::create(
            config('app.auth_cookie_name') . auth()->getDefaultDriver(),
            $token,
            $expired,
            null,
            null,
            env('APP_DEBUG') ? false : true,
            true,
            false,
            'Strict');
    }

    protected function successResponseNotPaginator($data = '', $message = null, $code = 200)
    {
        if ((isset($data->resource) && $data->resource instanceof LengthAwarePaginator) || $data instanceof LengthAwarePaginator)  {
            return response()->json([
                'data' => $data,
                'pagination' => [
                    'total' => $data->total(),
                    'count' => $data->count(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
                'error' => false,
                'message' => $message
            ], $code, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_INVALID_UTF8_IGNORE);
        }
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $data
        ], $code, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_INVALID_UTF8_IGNORE);
    }

    public function sendSuccessMultiArray($data = null, $total_payment = null, $message = 'Thành công!', $status = 200, $header = [])
    {
        if ($data instanceof AnonymousResourceCollection) {
            return response()->json([
                'data' => $data,
                'total' => $total_payment,
                'pagination' => [
                    'total' => $data->total(),
                    'count' => $data->count(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
                'error' => false,
                'message' => $message
            ], $status, $header);
        }
        return response()->json([
            'data' => $data,
            'error' => false,
            'message' => $message
        ], $status, $header);
    }
}
