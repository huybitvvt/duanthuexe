<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle($request, Closure $next)
    {
        $response = $request->isMethod('OPTIONS')
            ? response('', Response::HTTP_NO_CONTENT)
            : $next($request);

        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowAnyOrigin = in_array('*', $allowedOrigins, true);

        if ($origin && ($allowAnyOrigin || in_array($origin, $allowedOrigins, true))) {
            $response->headers->set('Access-Control-Allow-Origin', $allowAnyOrigin ? '*' : $origin);
            $response->headers->set('Vary', 'Origin', false);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN'
        );
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $response->headers->set('Access-Control-Max-Age', '86400');

        if (config('cors.supports_credentials', false) && !$allowAnyOrigin) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
