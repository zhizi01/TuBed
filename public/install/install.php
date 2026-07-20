<?php

declare(strict_types=1);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");
header('Referrer-Policy: no-referrer');

$rootPath = dirname(__DIR__, 2);
$envFile = $rootPath . DIRECTORY_SEPARATOR . '.env';
$schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'init.sql';
$lockFile = __DIR__ . DIRECTORY_SEPARATOR . 'install.lock';
$locked = is_file($lockFile);
$success = false;
$error = '';

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

$hostHeader = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$defaultBackendUrl = ($https ? 'https' : 'http') . '://' . ($hostHeader ?: 'localhost');
$defaultHost = (string) parse_url($defaultBackendUrl, PHP_URL_HOST);
$defaultFrontendUrl = in_array($defaultHost, ['127.0.0.1', 'localhost'], true)
    ? ($https ? 'https' : 'http') . '://' . $defaultHost . ':5173'
    : $defaultBackendUrl;
$values = [
    'db_host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
    'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
    'db_name' => trim((string) ($_POST['db_name'] ?? 'tubed')),
    'db_user' => trim((string) ($_POST['db_user'] ?? 'root')),
    'admin_username' => trim((string) ($_POST['admin_username'] ?? 'admin')),
    'admin_email' => strtolower(trim((string) ($_POST['admin_email'] ?? ''))),
    'backend_url' => rtrim(trim((string) ($_POST['backend_url'] ?? $defaultBackendUrl)), '/'),
    'frontend_url' => rtrim(trim((string) ($_POST['frontend_url'] ?? $defaultFrontendUrl)), '/'),
];

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quoteEnv(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function runInitSql(PDO $pdo, string $schemaFile): void
{
    $sql = file_get_contents($schemaFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('无法读取数据库初始化文件');
    }

    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $statements = preg_split('/;\s*(?:\r\n|\r|\n|$)/', trim((string) $sql));

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function buildEnvContent(array $config): string
{
    $lines = [
        'APP_DEBUG = false',
        '',
        'DB_DRIVER = mysql',
        'DB_TYPE = mysql',
        'DB_HOST = ' . quoteEnv($config['db_host']),
        'DB_NAME = ' . quoteEnv($config['db_name']),
        'DB_USER = ' . quoteEnv($config['db_user']),
        'DB_PASS = ' . quoteEnv($config['db_password']),
        'DB_PORT = ' . quoteEnv($config['db_port']),
        'DB_CHARSET = utf8mb4',
        '',
        'DEFAULT_LANG = zh-cn',
        '',
        'AUTH_ALLOW_REGISTER = true',
        'AUTH_FIRST_USER_ADMIN = false',
        'AUTH_TOKEN_TTL_DAYS = 30',
        'BACKEND_URL = ' . quoteEnv($config['backend_url']),
        'FRONTEND_URL = ' . quoteEnv($config['frontend_url']),
        'CORS_ALLOW_ORIGINS = ' . quoteEnv($config['frontend_url']),
        'PUBLIC_STORAGE_URL = ' . quoteEnv($config['backend_url'] . '/storage'),
        'UPLOAD_MAX_SIZE = 20971520',
        'UPLOAD_MAX_PIXELS = 100000000',
        '',
    ];

    return implode(PHP_EOL, $lines);
}

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = null;
    $envWritten = false;
    $lockWritten = false;
    $hadEnv = is_file($envFile);
    $previousEnv = $hadEnv ? file_get_contents($envFile) : null;

    try {
        $csrf = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['install_csrf'], $csrf)) {
            throw new RuntimeException('页面已过期，请刷新后重试');
        }

        if (PHP_VERSION_ID < 80000) {
            throw new RuntimeException('TuBed 需要 PHP 8.0 或更高版本');
        }
        foreach (['pdo_mysql', 'fileinfo', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException("请先启用 PHP {$extension} 扩展");
            }
        }
        if (!is_readable($schemaFile)) {
            throw new RuntimeException('public/install/init.sql 不可读取');
        }
        if (!is_writable($rootPath) || (is_file($envFile) && !is_writable($envFile))) {
            throw new RuntimeException('项目根目录或 .env 文件不可写');
        }
        if ($hadEnv && $previousEnv === false) {
            throw new RuntimeException('现有 .env 文件不可读取，无法安全覆盖');
        }
        if (!is_writable(__DIR__)) {
            throw new RuntimeException('public/install 目录不可写，无法创建安装锁');
        }

        $dbHost = $values['db_host'];
        $dbPort = (int) $values['db_port'];
        $dbName = $values['db_name'];
        $dbUser = $values['db_user'];
        $dbPassword = (string) ($_POST['db_password'] ?? '');
        $adminUsername = $values['admin_username'];
        $adminEmail = $values['admin_email'];
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $backendUrl = $values['backend_url'];
        $frontendUrl = $values['frontend_url'];

        if ($dbHost === '' || !preg_match('/^[A-Za-z0-9_.:\-\[\]]+$/', $dbHost)) {
            throw new RuntimeException('MySQL 主机地址格式不正确');
        }
        if ($dbPort < 1 || $dbPort > 65535) {
            throw new RuntimeException('MySQL 端口范围应为 1 至 65535');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
            throw new RuntimeException('数据库名只能包含字母、数字和下划线');
        }
        if ($dbUser === '' || strlen($dbUser) > 128) {
            throw new RuntimeException('请输入有效的 MySQL 用户名');
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $adminUsername)) {
            throw new RuntimeException('管理员用户名只能包含 3 至 32 位字母、数字和下划线');
        }
        if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('管理员邮箱格式不正确');
        }
        if (strlen($adminPassword) < 8 || strlen($adminPassword) > 72
            || !preg_match('/[A-Za-z]/', $adminPassword)
            || !preg_match('/\d/', $adminPassword)) {
            throw new RuntimeException('管理员密码需为 8 至 72 位，并同时包含字母和数字');
        }
        if (!hash_equals($adminPassword, $confirmPassword)) {
            throw new RuntimeException('两次输入的管理员密码不一致');
        }
        foreach ([
            '后端服务地址' => $backendUrl,
            '前端访问地址' => $frontendUrl,
        ] as $label => $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)
                || !in_array((string) parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                || parse_url($url, PHP_URL_QUERY) !== null
                || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                throw new RuntimeException("{$label}必须是有效且不带参数的 HTTP 或 HTTPS 地址");
            }
        }
        $frontendPath = (string) parse_url($frontendUrl, PHP_URL_PATH);
        if ($frontendPath !== '' && $frontendPath !== '/') {
            throw new RuntimeException('前端访问地址必须填写来源域名，不能包含页面路径');
        }

        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 8,
        ];
        $serverDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $serverPdo = new PDO($serverDsn, $dbUser, $dbPassword, $pdoOptions);
        $serverVersion = (string) $serverPdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (stripos($serverVersion, 'mariadb') !== false
            || (int) preg_replace('/^(\d+).*/', '$1', $serverVersion) < 8) {
            throw new RuntimeException('数据库版本不符合要求，请使用 MySQL 8.0 或更高版本');
        }

        $quotedDatabase = '`' . str_replace('`', '``', $dbName) . '`';
        try {
            $serverPdo->exec(
                "CREATE DATABASE IF NOT EXISTS {$quotedDatabase} "
                . 'DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_0900_ai_ci'
            );
        } catch (PDOException $createException) {
            // 部分托管数据库账号没有建库权限，数据库已存在时仍可继续。
            try {
                new PDO(
                    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                    $dbUser,
                    $dbPassword,
                    $pdoOptions
                );
            } catch (PDOException) {
                throw new RuntimeException('无法创建或连接目标数据库：' . $createException->getMessage());
            }
        }

        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            $pdoOptions
        );

        $tableStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = :database AND table_name = :table'
        );
        $tableStatement->execute(['database' => $dbName, 'table' => 'users']);
        if ((int) $tableStatement->fetchColumn() > 0
            && (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn() > 0) {
            throw new RuntimeException('目标数据库中已存在用户数据，请更换空数据库');
        }

        runInitSql($pdo, $schemaFile);
        $pdo->beginTransaction();

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('管理员密码哈希生成失败');
        }

        $adminStatement = $pdo->prepare(
            'INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `status`) '
            . 'VALUES (:username, :email, :password_hash, :role, 1)'
        );
        $adminStatement->execute([
            'username' => $adminUsername,
            'email' => $adminEmail !== '' ? $adminEmail : null,
            'password_hash' => $passwordHash,
            'role' => 'admin',
        ]);

        $envContent = buildEnvContent([
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPassword,
            'db_port' => (string) $dbPort,
            'backend_url' => $backendUrl,
            'frontend_url' => $frontendUrl,
        ]);
        if (file_put_contents($envFile, $envContent, LOCK_EX) === false) {
            throw new RuntimeException('写入 .env 配置文件失败');
        }
        $envWritten = true;

        $lockContent = json_encode([
            'installed_at' => date(DATE_ATOM),
            'php_version' => PHP_VERSION,
            'mysql_version' => $serverVersion,
            'database' => $dbName,
            'admin' => $adminUsername,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($lockContent === false
            || file_put_contents($lockFile, $lockContent . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('创建 install.lock 安装锁失败');
        }
        $lockWritten = true;

        $pdo->commit();
        $success = true;
        unset($_SESSION['install_csrf']);
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($lockWritten && is_file($lockFile)) {
            @unlink($lockFile);
        }
        if ($envWritten) {
            if (!$hadEnv) {
                @unlink($envFile);
            } else {
                @file_put_contents($envFile, (string) $previousEnv, LOCK_EX);
            }
        }
        $error = $exception->getMessage();
    }
}

$environment = [
    ['PHP 8.0+', PHP_VERSION_ID >= 80000, PHP_VERSION],
    ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? '已启用' : '未启用'],
    ['Fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? '已启用' : '未启用'],
    ['Mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? '已启用' : '未启用'],
    ['init.sql', is_readable($schemaFile), is_readable($schemaFile) ? '可读取' : '不可读取'],
    ['目录写入', is_writable($rootPath) && is_writable(__DIR__), '项目与安装目录'],
];
$environmentReady = !in_array(false, array_column($environment, 1), true);

if ($locked && !$success) {
    http_response_code(403);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TuBed 安装程序</title>
    <style>
        :root {
            color-scheme: light;
            --accent: #5e6ad2;
            --accent-soft: rgba(94, 106, 210, .1);
            --bg: #f5f6f8;
            --panel: #fff;
            --text: #17181c;
            --muted: #68707d;
            --border: #e2e5ea;
            --success: #238a57;
            --danger: #c94646;
        }
        * { box-sizing: border-box; }
        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 12% 14%, rgba(94, 106, 210, .09), transparent 28%),
                var(--bg);
            font: 14px/1.5 Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell {
            display: grid;
            width: min(1120px, calc(100% - 32px));
            min-height: calc(100vh - 64px);
            grid-template-columns: 330px minmax(0, 1fr);
            margin: 32px auto;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--panel);
            box-shadow: 0 24px 70px rgba(31, 35, 48, .1);
        }
        .intro {
            display: flex;
            flex-direction: column;
            padding: 38px 32px;
            color: #f5f6ff;
            background: linear-gradient(155deg, #1c1f30, #303568);
        }
        .brand { display: flex; align-items: center; gap: 11px; font-weight: 700; }
        .logo {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: var(--accent);
        }
        .logo::before {
            width: 16px;
            height: 13px;
            border: 2px solid white;
            border-radius: 3px;
            content: "";
        }
        .intro-copy { margin: auto 0; }
        .intro-copy small { color: #aeb2ff; font-size: 10px; font-weight: 700; letter-spacing: 1px; }
        .intro h1 { margin: 15px 0 12px; font-size: 35px; line-height: 1.08; letter-spacing: -1.4px; }
        .intro p { margin: 0; color: rgba(255,255,255,.64); font-size: 12px; line-height: 1.7; }
        .steps { display: grid; gap: 12px; margin-top: 34px; }
        .step { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,.64); font-size: 11px; }
        .step span {
            display: inline-flex;
            width: 25px;
            height: 25px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 7px;
            color: white;
        }
        .content { padding: 34px 38px 40px; }
        .heading { margin-bottom: 26px; }
        .heading h2 { margin: 0; font-size: 22px; letter-spacing: -.5px; }
        .heading p { margin: 6px 0 0; color: var(--muted); font-size: 11px; }
        .requirements {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 7px;
            margin-bottom: 24px;
        }
        .requirement { padding: 9px; border: 1px solid var(--border); border-radius: 9px; }
        .requirement strong, .requirement small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .requirement strong { font-size: 9px; }
        .requirement small { margin-top: 3px; color: var(--muted); font-size: 8px; }
        .requirement.ok { border-color: rgba(35,138,87,.24); background: rgba(35,138,87,.05); }
        .requirement.bad { border-color: rgba(201,70,70,.24); background: rgba(201,70,70,.05); }
        .notice { margin-bottom: 18px; padding: 11px 13px; border-radius: 9px; font-size: 11px; }
        .notice.error { color: var(--danger); background: rgba(201,70,70,.08); }
        .notice.success { color: var(--success); background: rgba(35,138,87,.08); }
        fieldset { min-width: 0; margin: 0 0 20px; padding: 0; border: 0; }
        legend { width: 100%; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 13px; }
        .field { display: grid; gap: 5px; min-width: 0; }
        .field.full { grid-column: 1 / -1; }
        label { color: var(--muted); font-size: 9px; font-weight: 600; }
        input {
            width: 100%;
            height: 39px;
            padding: 0 11px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: 0;
            color: var(--text);
            background: var(--panel);
            transition: border-color .16s, box-shadow .16s;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 8px; }
        .actions small { max-width: 380px; color: var(--muted); font-size: 9px; }
        button, .button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 17px;
            border: 0;
            border-radius: 8px;
            color: white;
            background: var(--accent);
            font: inherit;
            font-size: 11px;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }
        button:disabled { cursor: not-allowed; opacity: .48; }
        .result { display: flex; min-height: 480px; align-items: center; justify-content: center; flex-direction: column; text-align: center; }
        .result-mark {
            display: inline-flex;
            width: 64px;
            height: 64px;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            color: var(--success);
            background: rgba(35,138,87,.1);
            font-size: 28px;
        }
        .result h2 { margin: 18px 0 7px; font-size: 24px; }
        .result p { max-width: 440px; margin: 0 0 22px; color: var(--muted); font-size: 11px; }
        .locked .result-mark { color: var(--accent); background: var(--accent-soft); }
        code { padding: 3px 6px; border-radius: 5px; background: var(--bg); font-family: Consolas, monospace; font-size: 10px; }
        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .intro { min-height: 260px; }
            .intro-copy { margin: 40px 0 10px; }
            .steps { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 600px) {
            .shell { width: 100%; min-height: 100vh; margin: 0; border: 0; border-radius: 0; }
            .intro { padding: 26px 20px; }
            .intro h1 { font-size: 28px; }
            .steps { display: none; }
            .content { padding: 26px 18px 34px; }
            .requirements, .grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .actions { align-items: stretch; flex-direction: column; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="intro">
        <div class="brand"><span class="logo"></span><span>TuBed Installer</span></div>
        <div class="intro-copy">
            <small>INITIAL SETUP</small>
            <h1>配置你的<br>图片工作区。</h1>
            <p>安装程序会创建 MySQL 数据表、写入运行配置，并初始化首个超级管理员账号。</p>
            <div class="steps">
                <div class="step"><span>1</span>检查运行环境</div>
                <div class="step"><span>2</span>初始化数据库</div>
                <div class="step"><span>3</span>创建安装锁</div>
            </div>
        </div>
    </aside>

    <main class="content">
        <?php if ($success): ?>
            <section class="result">
                <span class="result-mark">✓</span>
                <h2>TuBed 安装成功</h2>
                <p>数据库、超级管理员和 <code>.env</code> 已初始化，并已创建 <code>install.lock</code> 防止重复安装。</p>
                <a class="button" href="<?= escapeHtml($values['frontend_url']) ?>/login">进入登录页</a>
            </section>
        <?php elseif ($locked): ?>
            <section class="result locked">
                <span class="result-mark">✓</span>
                <h2>系统已经安装</h2>
                <p>检测到 <code>public/install/install.lock</code>，安装程序已锁定。若确需重装，请先备份数据并手动删除锁文件。</p>
                <a class="button" href="/">返回站点首页</a>
            </section>
        <?php else: ?>
            <header class="heading">
                <h2>安装 TuBed</h2>
                <p>请填写 MySQL 8 连接信息和超级管理员账号。</p>
            </header>

            <section class="requirements">
                <?php foreach ($environment as [$label, $passed, $detail]): ?>
                    <article class="requirement <?= $passed ? 'ok' : 'bad' ?>">
                        <strong><?= $passed ? '✓' : '×' ?> <?= escapeHtml((string) $label) ?></strong>
                        <small><?= escapeHtml((string) $detail) ?></small>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($error !== ''): ?>
                <div class="notice error" role="alert"><?= escapeHtml($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml((string) $_SESSION['install_csrf']) ?>">

                <fieldset>
                    <legend>MySQL 连接</legend>
                    <div class="grid">
                        <div class="field">
                            <label for="db_host">主机地址</label>
                            <input id="db_host" name="db_host" required value="<?= escapeHtml($values['db_host']) ?>" placeholder="127.0.0.1">
                        </div>
                        <div class="field">
                            <label for="db_port">端口</label>
                            <input id="db_port" name="db_port" required inputmode="numeric" value="<?= escapeHtml($values['db_port']) ?>" placeholder="3306">
                        </div>
                        <div class="field">
                            <label for="db_name">数据库名</label>
                            <input id="db_name" name="db_name" required value="<?= escapeHtml($values['db_name']) ?>" placeholder="tubed">
                        </div>
                        <div class="field">
                            <label for="db_user">数据库用户</label>
                            <input id="db_user" name="db_user" required autocomplete="username" value="<?= escapeHtml($values['db_user']) ?>" placeholder="root">
                        </div>
                        <div class="field full">
                            <label for="db_password">数据库密码</label>
                            <input id="db_password" name="db_password" type="password" autocomplete="new-password" placeholder="请输入 MySQL 密码">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>超级管理员</legend>
                    <div class="grid">
                        <div class="field">
                            <label for="admin_username">管理员用户名</label>
                            <input id="admin_username" name="admin_username" required autocomplete="username" value="<?= escapeHtml($values['admin_username']) ?>" placeholder="admin">
                        </div>
                        <div class="field">
                            <label for="admin_email">管理员邮箱（选填）</label>
                            <input id="admin_email" name="admin_email" type="email" autocomplete="email" value="<?= escapeHtml($values['admin_email']) ?>" placeholder="admin@example.com">
                        </div>
                        <div class="field">
                            <label for="admin_password">管理员密码</label>
                            <input id="admin_password" name="admin_password" type="password" required autocomplete="new-password" placeholder="至少 8 位，包含字母和数字">
                        </div>
                        <div class="field">
                            <label for="confirm_password">确认密码</label>
                            <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password" placeholder="再次输入管理员密码">
                        </div>
                        <div class="field">
                            <label for="backend_url">后端服务地址</label>
                            <input id="backend_url" name="backend_url" type="url" required value="<?= escapeHtml($values['backend_url']) ?>" placeholder="https://api.example.com">
                        </div>
                        <div class="field">
                            <label for="frontend_url">前端访问地址</label>
                            <input id="frontend_url" name="frontend_url" type="url" required value="<?= escapeHtml($values['frontend_url']) ?>" placeholder="https://img.example.com">
                        </div>
                    </div>
                </fieldset>

                <div class="actions">
                    <small>安装仅适用于空数据库。成功后请保留 <code>install.lock</code>，并建议从 Web 服务器中禁用整个 install 目录。</small>
                    <button type="submit" <?= $environmentReady ? '' : 'disabled' ?>>开始安装</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
