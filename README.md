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

2. 创建本地配置：

```powershell
Copy-Item .example.env .env
```

Linux 或 macOS 可执行 `cp .example.env .env`。随后按实际环境修改数据库账号、跨域来源和存储访问地址。

3. 执行 `database/schema.sql`，创建 `tubed` 数据库及四张业务表。

4. 启动后端：

```bash
php think run
```

后端默认地址为 `http://localhost:8000`，健康检查接口为：

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
- `database/schema.sql`：MySQL 8 初始化结构
- `docs/API.md`：前端对接文档
- `public/storage`：本地公开图片存储目录，实际图片不会进入 Git

## 验证

```bash
composer test
```

该命令会执行不依赖数据库的模型冒烟测试，并验证所有 ThinkPHP 路由均可成功加载。完整接口联调前需先配置 MySQL 并执行初始化脚本。

## 上传安全

- 仅允许 JPG、PNG、GIF、WebP、AVIF，不接受可执行 SVG
- MIME 类型与真实图片内容会被双重校验
- 默认单文件上限 20 MB、总像素上限 1 亿
- 文件使用随机公开标识命名，不使用用户提交的路径
- 原始访问令牌只返回一次，数据库仅保存 SHA-256 摘要

详细请求参数和响应结构见 [API 文档](docs/API.md)。
