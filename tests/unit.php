<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\model\UserDbModel;
use app\model\UserTokenDbModel;
use app\model\ApiKeyDbModel;

(new \think\App(dirname(__DIR__)))->initialize();

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[失败] {$message}" . PHP_EOL);
        exit(1);
    }
}

$password = 'tubed-test-password';
$user = new UserDbModel([
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'storage_used' => 900,
    'storage_quota' => 1000,
]);

expectTrue($user->verifyPassword($password), '正确密码应通过验证');
expectTrue(!$user->verifyPassword('wrong-password'), '错误密码不应通过验证');
expectTrue($user->canStore(100), '剩余空间应允许边界大小文件');
expectTrue(!$user->canStore(101), '超出配额的文件应被拒绝');
expectTrue(!$user->canStore(0), '空文件应被拒绝');
expectTrue(UserTokenDbModel::findValid('') === null, '空令牌不应访问数据库');
expectTrue(ApiKeyDbModel::findValid('invalid-key') === null, '非法API密钥不应访问数据库');

$admin = new UserDbModel(['role' => 'admin']);
$regularUser = new UserDbModel(['role' => 'user']);
expectTrue($admin->hasPermission('admin.access'), '管理员应拥有全部权限');
expectTrue(!$regularUser->hasPermission('admin.access'), '普通用户不应拥有管理权限');
expectTrue($regularUser->hasPermission('images.manage'), '普通用户应能管理自己的图片');

echo '[通过] 模型冒烟测试完成' . PHP_EOL;
