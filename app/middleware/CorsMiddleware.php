<?php

namespace app\middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin' => (string) config('cors.allow_origin', '*'),
            'Access-Control-Allow-Methods' => (string) config('cors.allow_methods'),
            'Access-Control-Allow-Headers' => (string) config('cors.allow_headers'),
            'Access-Control-Max-Age' => (string) config('cors.max_age', 86400),
            'Vary' => 'Origin',
        ];

        if ((string) config('cors.expose_headers') !== '') {
            $headers['Access-Control-Expose-Headers'] = (string) config('cors.expose_headers');
        }

        if ($request->method() === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }
}
