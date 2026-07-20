<?php

namespace app\controller;

use app\model\ApiKeyDbModel;
use app\model\SystemSettingDbModel;
use app\Request;

class ApiKeyController
{
    public function index(Request $request)
    {
        $list = ApiKeyDbModel::where('user_id', $request->user->id)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => ['list' => $list],
        ]);
    }

    public function create(Request $request)
    {
        $name = trim((string) $request->param('name', ''));
        if ($name === '' || mb_strlen($name) > 60) {
            return json([
                'code' => 400,
                'message' => '密钥名称不能为空且不能超过60个字符',
                'data' => null,
            ], 400);
        }

        $maxKeys = max(1, (int) SystemSettingDbModel::valueOf(
            'open_api_max_keys_per_user',
            5
        ));
        $currentCount = ApiKeyDbModel::where('user_id', $request->user->id)->count();
        if ($currentCount >= $maxKeys) {
            return json([
                'code' => 409,
                'message' => "每个账号最多创建{$maxKeys}个API密钥",
                'data' => null,
            ], 409);
        }

        $expiresInDays = min(
            3650,
            max(1, (int) $request->param('expires_in_days', 365))
        );
        $issued = ApiKeyDbModel::issue(
            (int) $request->user->id,
            $name,
            (int) SystemSettingDbModel::valueOf('open_api_default_rate_limit', 60),
            (int) SystemSettingDbModel::valueOf('open_api_default_rate_window', 60),
            (int) SystemSettingDbModel::valueOf('open_api_default_total_limit', 10000),
            date('Y-m-d H:i:s', time() + $expiresInDays * 86400)
        );

        return json([
            'code' => 200,
            'message' => '创建成功，请立即保存密钥',
            'data' => [
                'secret' => $issued['key'],
                'api_key' => $issued['model']->toArray(),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $apiKey = $this->findOwnedKey($id, (int) $request->user->id);
        if (!$apiKey) {
            return json([
                'code' => 404,
                'message' => 'API密钥不存在',
                'data' => null,
            ], 404);
        }

        $param = $request->param();
        $data = [];

        if (array_key_exists('name', $param)) {
            $name = trim((string) $param['name']);
            if ($name === '' || mb_strlen($name) > 60) {
                return json([
                    'code' => 400,
                    'message' => '密钥名称不能为空且不能超过60个字符',
                    'data' => null,
                ], 400);
            }
            $data['name'] = $name;
        }

        if (array_key_exists('status', $param)) {
            $data['status'] = (int) $param['status'] === 1 ? 1 : 0;
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

    public function regenerate(Request $request, int $id)
    {
        $apiKey = $this->findOwnedKey($id, (int) $request->user->id);
        if (!$apiKey) {
            return json([
                'code' => 404,
                'message' => 'API密钥不存在',
                'data' => null,
            ], 404);
        }

        $plainKey = 'tb_' . bin2hex(random_bytes(24));
        $apiKey->save([
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => hash('sha256', $plainKey),
            'status' => 1,
        ]);

        return json([
            'code' => 200,
            'message' => '密钥已重置，请立即保存',
            'data' => [
                'secret' => $plainKey,
                'api_key' => $apiKey->toArray(),
            ],
        ]);
    }

    public function delete(Request $request, int $id)
    {
        $apiKey = $this->findOwnedKey($id, (int) $request->user->id);
        if (!$apiKey) {
            return json([
                'code' => 404,
                'message' => 'API密钥不存在',
                'data' => null,
            ], 404);
        }

        $apiKey->delete();

        return json([
            'code' => 200,
            'message' => '删除成功',
            'data' => null,
        ]);
    }

    private function findOwnedKey(int $id, int $userId)
    {
        return ApiKeyDbModel::where('id', $id)
            ->where('user_id', $userId)
            ->find();
    }
}
