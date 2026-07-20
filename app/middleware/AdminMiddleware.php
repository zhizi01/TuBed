<?php

namespace app\middleware;

use Closure;

class AdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!$request->user || !$request->user->hasPermission('admin.access')) {
            return json([
                'code' => 403,
                'message' => '需要管理员权限',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
