# TuBed 图床系统

TuBed 是一个基于 ThinkPHP 8、MySQL 8 和 Vue 3 的图床项目。当前后端已提供用户认证、相册管理、图片上传与管理、存储配额和用量统计接口，前端脚手架位于 `app-dist`。

## 环境要求

- PHP 8.0+，启用 `pdo_mysql`、`fileinfo`、`mbstring`
- MySQL 8.0+
- Composer 2
- Node.js 20.19+（仅前端开发需要）

## 本地启动

1. 安装后端依赖：

```bash
composer install
```

2. 启动后端：

```bash
php think run
```

3. 浏览器访问 `http://localhost:8000/install/install.php`，填写 MySQL 8
   连接信息、站点地址和超级管理员账号密码。

安装程序会统一执行 `public/install/init.sql`、写入根目录 `.env`，并在成功后创建
`public/install/install.lock`。检测到锁文件后安装程序会拒绝重复安装。
Apache 和本地开发路由已禁止直接下载 `.sql`、`.lock`；使用 Nginx 时也应拒绝访问
这两类文件，并建议安装完成后禁用整个 `/install` 路径。

4. 健康检查：

```text
GET http://localhost:8000/api/v1/health
```

5. 启动前端：

```bash
cd app-dist
npm install
npm run dev
```

## 核心目录

- `app/controller`：认证、相册、图片和统计控制器
- `app/model`：MySQL 数据模型及少量领域逻辑
- `app/middleware`：Bearer Token 认证和跨域中间件
- `config/upload.php`：上传大小、像素和格式白名单
- `public/install/install.php`：Web 安装程序
- `public/install/init.sql`：唯一的 MySQL 8 初始化结构
- `docs/API.md`：前端对接文档
- `design-system/tubed/MASTER.md`：后台 UI 与交互规范
- `public/storage`：本地公开图片存储目录，实际图片不会进入 Git

## 角色与开放 API

- 安装程序创建首个超级管理员，后续注册账号默认为普通用户
- 管理员可管理用户角色、账号状态、存储配额、全站图片和 API 策略
- 普通用户只能管理自己的图片、相册、个人信息和 API 密钥
- 前端通过路由、菜单和 `v-permission` 指令做三层权限展示，后端中间件再次强制校验
- 开放上传地址为 `POST /api/open/v1/images`，使用 `X-API-Key` 请求头
- 每个 API 密钥独立限制窗口访问频率、总调用次数、状态和过期时间

## 验证

```bash
composer test
```

该命令会执行模型冒烟测试、安装脚本语法检查，并验证所有 ThinkPHP 路由均可成功加载。

## 上传安全

- 仅允许 JPG、PNG、GIF、WebP、AVIF，不接受可执行 SVG
- MIME 类型与真实图片内容会被双重校验
- 默认单文件上限 20 MB、总像素上限 1 亿
- 文件使用随机公开标识命名，不使用用户提交的路径
- 原始访问令牌只返回一次，数据库仅保存 SHA-256 摘要

详细请求参数和响应结构见 [API 文档](docs/API.md)。
