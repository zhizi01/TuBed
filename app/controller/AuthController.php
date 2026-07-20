<?php

namespace app\controller;

use app\model\UserDbModel;
use app\model\UserTokenDbModel;
use app\Request;
use think\facade\Db;
use think\facade\Log;
use Throwable;

class AuthController
{
    public function register(Request $request)
    {
        if (!config('auth.allow_register', true)) {
            return json([
                'code' => 403,
                'message' => '系统暂未开放注册',
                'data' => null,
            ], 403);
        }

        $param = $request->param();
        $username = trim((string) ($param['username'] ?? ''));
        $email = strtolower(trim((string) ($param['email'] ?? '')));
        $password = (string) ($param['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            return json([
                'code' => 400,
                'message' => '用户名只能包含字母、数字和下划线，长度为3至32位',
                'data' => null,
            ], 400);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json([
                'code' => 400,
                'message' => '邮箱格式不正确',
                'data' => null,
            ], 400);
        }

        if (strlen($password) < 8 || strlen($password) > 72) {
            return json([
                'code' => 400,
                'message' => '密码长度应为8至72位',
                'data' => null,
            ], 400);
        }

        if (UserDbModel::where('username', $username)->find()) {
            return json([
                'code' => 409,
                'message' => '用户名已被使用',
                'data' => null,
            ], 409);
        }

        if ($email !== '' && UserDbModel::where('email', $email)->find()) {
            return json([
                'code' => 409,
                'message' => '邮箱已被使用',
                'data' => null,
            ], 409);
        }

        try {
            [$user, $issued] = Db::transaction(function () use ($username, $email, $password) {
                $role = config('auth.first_user_admin', false)
                    && UserDbModel::count() === 0
                    ? 'admin'
                    : 'user';
                $user = UserDbModel::create([
                    'username' => $username,
                    'email' => $email ?: null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => 1,
                ]);
                $issued = UserTokenDbModel::issue(
                    (int) $user->id,
                    'web',
                    (int) config('auth.token_ttl_days', 30)
                );

                return [$user, $issued];
            });
        } catch (Throwable $exception) {
            Log::error('用户注册失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '注册失败，请稍后重试',
                'data' => null,
            ], 500);
        }

        return json([
            'code' => 200,
            'message' => '注册成功',
            'data' => $this->buildAuthData($user, $issued),
        ]);
    }

    public function login(Request $request)
    {
        $param = $request->param();
        $identifier = trim((string) ($param['identifier'] ?? ''));
        $password = (string) ($param['password'] ?? '');
        $tokenName = trim((string) ($param['token_name'] ?? 'web'));

        if ($identifier === '' || $password === '') {
            return json([
                'code' => 400,
                'message' => '账号和密码不能为空',
                'data' => null,
            ], 400);
        }

        $user = UserDbModel::findByIdentifier($identifier);
        if (!$user || !$user->verifyPassword($password)) {
            return json([
                'code' => 401,
                'message' => '账号或密码错误',
                'data' => null,
            ], 401);
        }

        if ((int) $user->status !== 1) {
            return json([
                'code' => 403,
                'message' => '账号已被禁用',
                'data' => null,
            ], 403);
        }

        try {
            if (password_needs_rehash((string) $user->getData('password_hash'), PASSWORD_DEFAULT)) {
                $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            }

            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = $request->ip();
            $user->save();

            UserTokenDbModel::where('user_id', $user->id)
                ->where('expires_at', '<=', date('Y-m-d H:i:s'))
                ->delete();

            $issued = UserTokenDbModel::issue(
                (int) $user->id,
                $tokenName,
                (int) config('auth.token_ttl_days', 30)
            );
        } catch (Throwable $exception) {
            Log::error('用户登录失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '登录失败，请稍后重试',
                'data' => null,
            ], 500);
        }

        return json([
            'code' => 200,
            'message' => '登录成功',
            'data' => $this->buildAuthData($user, $issued),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user;

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => $this->buildUserData($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->accessToken->delete();

        return json([
            'code' => 200,
            'message' => '已退出登录',
            'data' => null,
        ]);
    }

    private function buildAuthData(UserDbModel $user, array $issued): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $issued['token'],
            'expires_at' => (string) $issued['model']->getData('expires_at'),
            'user' => $this->buildUserData($user),
        ];
    }

    private function buildUserData(UserDbModel $user): array
    {
        $data = $user->toArray();
        $data['role_label'] = $user->getRoleLabel();
        $data['permissions'] = $user->getPermissions();
        $data['storage_remaining'] = max(
            0,
            (int) $user->storage_quota - (int) $user->storage_used
        );
        $data['storage_percent'] = (int) $user->storage_quota > 0
            ? round((int) $user->storage_used * 100 / (int) $user->storage_quota, 2)
            : 0;

        return $data;
    }
}
