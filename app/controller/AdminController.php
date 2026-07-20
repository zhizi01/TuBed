<?php

namespace app\controller;

use app\model\ApiKeyDbModel;
use app\model\ImageDbModel;
use app\model\SystemSettingDbModel;
use app\model\UserDbModel;
use app\model\UserTokenDbModel;
use app\Request;
use RuntimeException;
use think\facade\Db;
use think\facade\Filesystem;
use think\facade\Log;
use Throwable;

class AdminController
{
    public function overview()
    {
        $imageSize = (int) ImageDbModel::sum('file_size');

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'users' => [
                    'total' => UserDbModel::count(),
                    'active' => UserDbModel::where('status', 1)->count(),
                    'admins' => UserDbModel::where('role', 'admin')->count(),
                ],
                'images' => [
                    'total' => ImageDbModel::count(),
                    'size' => $imageSize,
                ],
                'api_keys' => [
                    'total' => ApiKeyDbModel::count(),
                    'active' => ApiKeyDbModel::where('status', 1)->count(),
                    'used_count' => (int) ApiKeyDbModel::sum('used_count'),
                ],
                'open_api' => $this->readOpenApiSettings(),
                'recent_users' => UserDbModel::order('id', 'desc')
                    ->limit(5)
                    ->select()
                    ->toArray(),
            ],
        ]);
    }

    public function users(Request $request)
    {
        $param = $request->param();
        $page = max(1, (int) ($param['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($param['page_size'] ?? 20)));
        $query = UserDbModel::where([]);

        $keyword = trim((string) ($param['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('username|email', 'like', '%' . $keyword . '%');
        }

        $role = trim((string) ($param['role'] ?? ''));
        if (in_array($role, ['admin', 'user'], true)) {
            $query->where('role', $role);
        }

        if (array_key_exists('status', $param) && $param['status'] !== '') {
            $query->where('status', (int) $param['status'] === 1 ? 1 : 0);
        }

        $total = (clone $query)->count();
        $list = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => compact('list', 'total', 'page', 'pageSize'),
        ]);
    }

    public function updateUser(Request $request, int $id)
    {
        $user = UserDbModel::where('id', $id)->find();
        if (!$user) {
            return json([
                'code' => 404,
                'message' => '用户不存在',
                'data' => null,
            ], 404);
        }

        $param = $request->param();
        $data = [];

        if (array_key_exists('role', $param)) {
            $role = (string) $param['role'];
            if (!in_array($role, ['admin', 'user'], true)) {
                return json([
                    'code' => 400,
                    'message' => '角色参数不正确',
                    'data' => null,
                ], 400);
            }
            $data['role'] = $role;
        }

        if (array_key_exists('status', $param)) {
            $data['status'] = (int) $param['status'] === 1 ? 1 : 0;
        }

        if (array_key_exists('storage_quota', $param)) {
            $quota = max(0, (int) $param['storage_quota']);
            if ($quota < (int) $user->storage_used) {
                return json([
                    'code' => 400,
                    'message' => '存储配额不能小于用户已用空间',
                    'data' => null,
                ], 400);
            }
            $data['storage_quota'] = $quota;
        }

        if ((int) $user->id === (int) $request->user->id
            && (($data['role'] ?? 'admin') !== 'admin' || ($data['status'] ?? 1) !== 1)) {
            return json([
                'code' => 400,
                'message' => '不能降级或禁用当前管理员账号',
                'data' => null,
            ], 400);
        }

        $removingAdmin = $user->role === 'admin'
            && (($data['role'] ?? 'admin') !== 'admin' || ($data['status'] ?? 1) !== 1);
        if ($removingAdmin && UserDbModel::where('role', 'admin')->where('status', 1)->count() <= 1) {
            return json([
                'code' => 409,
                'message' => '系统至少需要保留一个可用管理员',
                'data' => null,
            ], 409);
        }

        if ($data !== []) {
            Db::transaction(function () use ($user, $data) {
                $user->save($data);
                if (($data['status'] ?? 1) === 0) {
                    UserTokenDbModel::where('user_id', $user->id)->delete();
                    ApiKeyDbModel::where('user_id', $user->id)->update(['status' => 0]);
                }
            });
        }

        return json([
            'code' => 200,
            'message' => '保存成功',
            'data' => $user->toArray(),
        ]);
    }

    public function images(Request $request)
    {
        $param = $request->param();
        $page = max(1, (int) ($param['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($param['page_size'] ?? 20)));
        $query = ImageDbModel::where([]);

        $keyword = trim((string) ($param['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('original_name|title', 'like', '%' . $keyword . '%');
        }
        if ((int) ($param['user_id'] ?? 0) > 0) {
            $query->where('user_id', (int) $param['user_id']);
        }

        $total = (clone $query)->count();
        $images = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $list = [];
        foreach ($images as $image) {
            $item = $image->toArray();
            $owner = UserDbModel::where('id', $image->user_id)->find();
            $item['user_id'] = (int) $image->user_id;
            $item['username'] = $owner?->username;
            $list[] = $item;
        }

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => compact('list', 'total', 'page', 'pageSize'),
        ]);
    }

    public function deleteImage(int $id)
    {
        try {
            [$diskName, $storagePath] = Db::transaction(function () use ($id) {
                $image = ImageDbModel::where('id', $id)->lock(true)->find();
                if (!$image) {
                    throw new RuntimeException('IMAGE_NOT_FOUND');
                }

                $user = UserDbModel::where('id', $image->user_id)->lock(true)->find();
                $diskName = (string) $image->storage_disk;
                $storagePath = (string) $image->storage_path;
                $fileSize = (int) $image->file_size;
                $image->delete();

                if ($user) {
                    $user->save([
                        'storage_used' => max(0, (int) $user->storage_used - $fileSize),
                    ]);
                }

                return [$diskName, $storagePath];
            });
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'IMAGE_NOT_FOUND') {
                return json([
                    'code' => 404,
                    'message' => '图片不存在',
                    'data' => null,
                ], 404);
            }

            Log::error('管理员删除图片失败：' . $exception->getMessage());
            return json([
                'code' => 500,
                'message' => '删除图片失败',
                'data' => null,
            ], 500);
        }

        try {
            Filesystem::disk($diskName)->delete($storagePath);
        } catch (Throwable $exception) {
            Log::warning('删除存储文件失败：' . $exception->getMessage());
        }

        return json([
            'code' => 200,
            'message' => '删除成功',
            'data' => null,
        ]);
    }

    public function apiSettings()
    {
        return json([
            'code' => 200,
            'message' => 'success',
            'data' => $this->readOpenApiSettings(),
        ]);
    }

    public function updateApiSettings(Request $request)
    {
        $param = $request->param();
        $definitions = [
            'open_api_enabled' => ['boolean', 0, 1],
            'open_api_upload_enabled' => ['boolean', 0, 1],
            'open_api_default_rate_limit' => ['integer', 1, 10000],
            'open_api_default_rate_window' => ['integer', 1, 86400],
            'open_api_default_total_limit' => ['integer', 0, 1000000000],
            'open_api_max_keys_per_user' => ['integer', 1, 100],
        ];

        foreach ($definitions as $key => [$type, $min, $max]) {
            if (!array_key_exists($key, $param)) {
                continue;
            }

            $value = $type === 'boolean'
                ? ((int) $param[$key] === 1)
                : (int) $param[$key];
            if ($type === 'integer' && ($value < $min || $value > $max)) {
                return json([
                    'code' => 400,
                    'message' => "{$key} 超出允许范围",
                    'data' => null,
                ], 400);
            }
            SystemSettingDbModel::saveValue($key, $value, $type);
        }

        return json([
            'code' => 200,
            'message' => '配置已保存',
            'data' => $this->readOpenApiSettings(),
        ]);
    }

    public function apiKeys(Request $request)
    {
        $param = $request->param();
        $page = max(1, (int) ($param['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($param['page_size'] ?? 20)));
        $query = ApiKeyDbModel::where([]);

        if ((int) ($param['user_id'] ?? 0) > 0) {
            $query->where('user_id', (int) $param['user_id']);
        }
        if (array_key_exists('status', $param) && $param['status'] !== '') {
            $query->where('status', (int) $param['status'] === 1 ? 1 : 0);
        }

        $total = (clone $query)->count();
        $keys = $query->order('id', 'desc')->page($page, $pageSize)->select();
        $list = [];
        foreach ($keys as $key) {
            $item = $key->toArray();
            $owner = UserDbModel::where('id', $key->user_id)->find();
            $item['username'] = $owner?->username;
            $list[] = $item;
        }

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => compact('list', 'total', 'page', 'pageSize'),
        ]);
    }

    public function updateApiKey(Request $request, int $id)
    {
        $apiKey = ApiKeyDbModel::where('id', $id)->find();
        if (!$apiKey) {
            return json([
                'code' => 404,
                'message' => 'API密钥不存在',
                'data' => null,
            ], 404);
        }

        $param = $request->param();
        $data = [];
        foreach (['rate_limit', 'rate_window', 'total_limit'] as $field) {
            if (array_key_exists($field, $param)) {
                $data[$field] = max($field === 'total_limit' ? 0 : 1, (int) $param[$field]);
            }
        }
        if (array_key_exists('status', $param)) {
            $data['status'] = (int) $param['status'] === 1 ? 1 : 0;
        }
        if (array_key_exists('expires_at', $param)) {
            $expiresAt = trim((string) $param['expires_at']);
            if ($expiresAt !== '' && strtotime($expiresAt) === false) {
                return json([
                    'code' => 400,
                    'message' => '过期时间格式不正确',
                    'data' => null,
                ], 400);
            }
            $data['expires_at'] = $expiresAt ?: null;
        }

        if ($data !== []) {
            $apiKey->save($data);
        }

        return json([
            'code' => 200,
            'message' => '保存成功',
            'data' => $apiKey->toArray(),
        ]);
    }

    private function readOpenApiSettings(): array
    {
        return [
            'open_api_enabled' => SystemSettingDbModel::valueOf('open_api_enabled', true),
            'open_api_upload_enabled' => SystemSettingDbModel::valueOf(
                'open_api_upload_enabled',
                true
            ),
            'open_api_default_rate_limit' => SystemSettingDbModel::valueOf(
                'open_api_default_rate_limit',
                60
            ),
            'open_api_default_rate_window' => SystemSettingDbModel::valueOf(
                'open_api_default_rate_window',
                60
            ),
            'open_api_default_total_limit' => SystemSettingDbModel::valueOf(
                'open_api_default_total_limit',
                10000
            ),
            'open_api_max_keys_per_user' => SystemSettingDbModel::valueOf(
                'open_api_max_keys_per_user',
                5
            ),
        ];
    }
}
