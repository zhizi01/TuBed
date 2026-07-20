<?php

namespace app\middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $origin = rtrim(trim((string) $request->header('origin', '')), '/');
        $allowedOrigins = (array) config('cors.allow_origins', []);
        $allowAnyOrigin = in_array('*', $allowedOrigins, true);
        $originAllowed = $origin === ''
            || $allowAnyOrigin
            || in_array($origin, $allowedOrigins, true);

        if (!$originAllowed) {
            return json([
                'code' => 403,
                'message' => '当前前端地址不在 CORS 白名单中',
                'data' => null,
            ], 403)->header(['Vary' => 'Origin']);
        }

        $headers = [
            'Access-Control-Allow-Methods' => (string) config('cors.allow_methods'),
            'Access-Control-Allow-Headers' => (string) config('cors.allow_headers'),
            'Access-Control-Max-Age' => (string) config('cors.max_age', 86400),
        ];

        if ($origin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowAnyOrigin ? '*' : $origin;
            if (!$allowAnyOrigin) {
                $headers['Vary'] = 'Origin';
            }
        }

        if ((string) config('cors.expose_headers') !== '') {
            $headers['Access-Control-Expose-Headers'] = (string) config('cors.expose_headers');
        }

        if ($request->method() === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }
}
