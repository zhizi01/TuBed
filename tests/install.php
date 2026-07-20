<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$installer = file_get_contents($root . '/public/install/install.php');
$schema = file_get_contents($root . '/public/install/init.sql');
$gitignore = file_get_contents($root . '/.gitignore');

function expectInstall(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[失败] {$message}" . PHP_EOL);
        exit(1);
    }
}

expectInstall($installer !== false && $installer !== '', '安装脚本必须存在');
expectInstall($schema !== false && $schema !== '', '统一 init.sql 必须存在');

foreach ([
    'users',
    'user_tokens',
    'albums',
    'images',
    'api_keys',
    'api_rate_buckets',
    'system_settings',
] as $table) {
    expectInstall(
        str_contains($schema, "CREATE TABLE IF NOT EXISTS `{$table}`"),
        "init.sql 缺少 {$table} 表"
    );
}

expectInstall(str_contains($installer, 'install.lock'), '安装脚本必须创建安装锁');
expectInstall(str_contains($installer, 'password_hash'), '超级管理员密码必须安全哈希');
expectInstall(str_contains($installer, "'.env'"), '安装脚本必须生成 .env');
expectInstall(str_contains($installer, 'BACKEND_URL'), '安装脚本必须写入后端服务地址');
expectInstall(str_contains($installer, 'FRONTEND_URL'), '安装脚本必须写入前端访问地址');
expectInstall(str_contains($installer, 'CORS_ALLOW_ORIGINS'), '安装脚本必须写入 CORS 白名单');
expectInstall(str_contains($installer, 'hash_equals'), '安装表单必须验证 CSRF 令牌');
expectInstall(
    str_contains((string) $gitignore, '/public/install/install.lock'),
    'install.lock 必须被 Git 忽略'
);
expectInstall(!is_file($root . '/database/schema.sql'), '不得保留旧 schema.sql');

echo '[通过] Web 安装器契约测试完成' . PHP_EOL;
