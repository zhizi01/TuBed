<?php

namespace app\middleware;

use app\model\UserDbModel;
use app\model\UserTokenDbModel;
use Closure;

class AuthMiddleware
{
    public function handle($request, Closure $next)
    {
        $authorization = trim((string) $request->header('authorization', ''));
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return json([
                'code' => 401,
                'message' => '请先登录',
                'data' => null,
            ], 401)->header(['WWW-Authenticate' => 'Bearer']);
        }

        $accessToken = UserTokenDbModel::findValid($matches[1]);
        if (!$accessToken) {
            return json([
                'code' => 401,
                'message' => '登录凭证无效或已过期',
                'data' => null,
            ], 401)->header(['WWW-Authenticate' => 'Bearer']);
        }

        $user = UserDbModel::where('id', $accessToken->user_id)->find();
        if (!$user || (int) $user->status !== 1) {
            return json([
                'code' => 403,
                'message' => '账号已被禁用',
                'data' => null,
            ], 403);
        }

        $request->user = $user;
        $request->accessToken = $accessToken;
        $request->authType = 'token';

        // 降低写库频率，每五分钟刷新一次令牌使用时间。
        $lastUsedAt = $accessToken->getData('last_used_at');
        if (!$lastUsedAt || strtotime((string) $lastUsedAt) <= time() - 300) {
            $accessToken->save(['last_used_at' => date('Y-m-d H:i:s')]);
        }

        return $next($request);
    }
}
