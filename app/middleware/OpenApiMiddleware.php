<?php

namespace app\middleware;

use app\model\ApiKeyDbModel;
use app\model\SystemSettingDbModel;
use app\model\UserDbModel;
use Closure;
use think\facade\Db;
use think\facade\Log;
use Throwable;

class OpenApiMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            if (!SystemSettingDbModel::valueOf('open_api_enabled', true)) {
                return json([
                    'code' => 503,
                    'message' => '开放API暂未启用',
                    'data' => null,
                ], 503);
            }

            if (str_ends_with(trim($request->pathinfo(), '/'), 'images')
                && !SystemSettingDbModel::valueOf('open_api_upload_enabled', true)) {
                return json([
                    'code' => 403,
                    'message' => '开放API上传已关闭',
                    'data' => null,
                ], 403);
            }

            $plainKey = trim((string) $request->header('x-api-key', ''));
            $apiKey = ApiKeyDbModel::findValid($plainKey);
            if (!$apiKey) {
                return json([
                    'code' => 401,
                    'message' => 'API密钥无效、已停用或已过期',
                    'data' => null,
                ], 401);
            }

            $user = UserDbModel::where('id', $apiKey->user_id)->find();
            if (!$user || (int) $user->status !== 1) {
                return json([
                    'code' => 403,
                    'message' => '密钥所属账号不可用',
                    'data' => null,
                ], 403);
            }

            $rateWindow = max(1, (int) $apiKey->rate_window);
            $windowTimestamp = intdiv(time(), $rateWindow) * $rateWindow;
            $windowStart = date('Y-m-d H:i:s', $windowTimestamp);

            Db::execute(
                'INSERT INTO `api_rate_buckets` (`api_key_id`, `window_start`, `request_count`) '
                . 'VALUES (:api_key_id, :window_start, 1) '
                . 'ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1',
                ['api_key_id' => $apiKey->id, 'window_start' => $windowStart]
            );

            $windowCount = (int) Db::name('api_rate_buckets')
                ->where('api_key_id', $apiKey->id)
                ->where('window_start', $windowStart)
                ->value('request_count');

            // 小概率清理过期窗口，避免限流表长期增长。
            if (random_int(1, 1000) === 1) {
                Db::name('api_rate_buckets')
                    ->where('window_start', '<', date('Y-m-d H:i:s', time() - 172800))
                    ->delete();
            }

            if ($windowCount > (int) $apiKey->rate_limit) {
                $retryAfter = max(1, $windowTimestamp + $rateWindow - time());

                return json([
                    'code' => 429,
                    'message' => '访问过于频繁，请稍后重试',
                    'data' => ['retry_after' => $retryAfter],
                ], 429)->header([
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $apiKey->rate_limit,
                    'X-RateLimit-Remaining' => '0',
                ]);
            }

            $updateSql = 'UPDATE `api_keys` SET `used_count` = `used_count` + 1, '
                . '`last_used_at` = :last_used_at WHERE `id` = :id';
            if ((int) $apiKey->total_limit > 0) {
                $updateSql .= ' AND `used_count` < `total_limit`';
            }

            $affected = Db::execute($updateSql, [
                'last_used_at' => date('Y-m-d H:i:s'),
                'id' => $apiKey->id,
            ]);
            if ($affected !== 1) {
                return json([
                    'code' => 429,
                    'message' => 'API密钥调用次数已用尽',
                    'data' => null,
                ], 429);
            }

            $apiKey->used_count = (int) $apiKey->used_count + 1;
            $request->user = $user;
            $request->apiKey = $apiKey;
            $request->authType = 'api_key';

            $response = $next($request);
            $headers = [
                'X-RateLimit-Limit' => (string) $apiKey->rate_limit,
                'X-RateLimit-Remaining' => (string) max(
                    0,
                    (int) $apiKey->rate_limit - $windowCount
                ),
                'X-RateLimit-Window' => (string) $rateWindow,
            ];

            if ((int) $apiKey->total_limit > 0) {
                $headers['X-Usage-Limit'] = (string) $apiKey->total_limit;
                $headers['X-Usage-Remaining'] = (string) max(
                    0,
                    (int) $apiKey->total_limit - (int) $apiKey->used_count
                );
            }

            return $response->header($headers);
        } catch (Throwable $exception) {
            Log::error('开放API认证失败：' . $exception->getMessage());

            return json([
                'code' => 503,
                'message' => '开放API暂时不可用',
                'data' => null,
            ], 503);
        }
    }
}
