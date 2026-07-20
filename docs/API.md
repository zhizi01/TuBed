# TuBed API 对接文档

## 通用约定

- 接口前缀：`/api/v1`
- 请求和响应编码：UTF-8
- JSON 请求需发送 `Content-Type: application/json`
- 上传接口使用 `multipart/form-data`
- 除注册、登录和健康检查外，均需发送：

```http
Authorization: Bearer <access_token>
```

统一响应结构：

```json
{
  "code": 200,
  "message": "success",
  "data": {}
}
```

`code` 与 HTTP 状态码一致。常见错误包括 400 参数错误、401 未登录、403 账号禁用、404 资源不存在、409 数据冲突、413 文件或配额超限、415 格式不支持、422 图片内容无效、500 服务异常。

## 公共接口

### 健康检查

`GET /api/v1/health`

数据库可用时返回 200；数据库连接失败时返回 503。

### 注册

`POST /api/v1/auth/register`

```json
{
  "username": "demo_user",
  "email": "demo@example.com",
  "password": "password123"
}
```

- `username`：必填，3 至 32 位字母、数字或下划线
- `email`：选填
- `password`：必填，8 至 72 位

成功响应的 `data` 包含 `access_token`、`expires_at` 和 `user`。

### 登录

`POST /api/v1/auth/login`

```json
{
  "identifier": "demo_user",
  "password": "password123",
  "token_name": "web"
}
```

`identifier` 可填写用户名或邮箱。`token_name` 选填，用于区分 Web、桌面端等登录来源。

## 账号接口

### 当前用户

`GET /api/v1/auth/me`

除用户资料外，还会返回 `role`、`role_label`、`permissions`、
`storage_remaining` 和 `storage_percent`。管理员的权限数组包含 `*`。

### 退出登录

`POST /api/v1/auth/logout`

仅撤销当前 Bearer Token，不影响其他设备上的令牌。

## 统计接口

### 概览

`GET /api/v1/stats/overview`

返回图片数量、相册数量、近 7 天上传数量、存储配额以及按扩展名汇总的数据。

## 相册接口

### 相册列表

`GET /api/v1/albums`

每个相册包含 `image_count`。

### 创建相册

`POST /api/v1/albums`

```json
{
  "name": "旅行",
  "description": "旅途中拍摄的图片"
}
```

同一用户不能创建重名相册。

### 更新相册

`PUT|PATCH /api/v1/albums/{id}`

`name` 和 `description` 均为选填，只更新提交的字段。

### 删除相册

`DELETE /api/v1/albums/{id}`

只删除相册，原有图片会移动到“未分类”，不会删除图片文件。

## 图片接口

### 图片列表

`GET /api/v1/images`

支持以下查询参数：

- `page`：页码，默认 1
- `page_size`：每页数量，默认 24，最大 100
- `album_id`：相册 ID；传 0 查询未分类图片
- `keyword`：按标题或原始文件名搜索
- `mime_type`：按完整 MIME 类型筛选，如 `image/png`

响应的 `data` 包含 `list`、`total`、`page` 和 `page_size`。

### 上传图片

`POST /api/v1/images`

表单字段：

- `file`：必填，单张图片文件
- `title`：选填，最多 100 个字符
- `album_id`：选填，目标相册 ID

默认支持 JPG、PNG、GIF、WebP、AVIF，单文件上限 20 MB。多图上传时前端可并发调用该接口，并根据每次响应分别展示结果。

成功响应中的 `url` 是可直接访问或复制的图片地址：

```json
{
  "code": 200,
  "message": "上传成功",
  "data": {
    "id": 1,
    "public_id": "a12b34c56d78e90f...",
    "original_name": "photo.png",
    "mime_type": "image/png",
    "file_size": 102400,
    "width": 1920,
    "height": 1080,
    "url": "http://localhost:8000/storage/images/1/2026/07/a12b34c56d78e90f.png"
  }
}
```

### 图片详情

`GET /api/v1/images/{id}`

只能读取当前用户自己的图片元数据。

### 更新图片

`PUT|PATCH /api/v1/images/{id}`

```json
{
  "title": "新的标题",
  "album_id": 2
}
```

将 `album_id` 设为 `null`、空字符串或 0，可移至未分类。

### 删除图片

`DELETE /api/v1/images/{id}`

删除元数据并扣减已用空间，同时清理本地存储文件。

## API 密钥接口

以下接口使用用户 Bearer Token。

- `GET /api/v1/api-keys`：当前用户的密钥列表
- `POST /api/v1/api-keys`：创建密钥；参数为 `name`、`expires_in_days`
- `PATCH /api/v1/api-keys/{id}`：修改名称或启用状态
- `POST /api/v1/api-keys/{id}/regenerate`：重置原始密钥
- `DELETE /api/v1/api-keys/{id}`：删除密钥

完整密钥只在创建或重置响应的 `data.secret` 中出现一次。

## 开放上传接口

`POST /api/open/v1/images`

该接口不使用用户 Bearer Token，请发送：

```http
X-API-Key: tb_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: multipart/form-data
```

表单字段与登录后的图片上传接口相同。每次访问都会计入密钥的窗口请求数和累计调用次数。响应头包含：

- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Window`
- `X-Usage-Limit`
- `X-Usage-Remaining`
- 超出频率时额外返回 `Retry-After`

可通过 `GET /api/open/v1/ping` 验证密钥，验证请求同样计入额度。

## 管理员接口

以下接口同时经过 Bearer Token 认证和管理员权限中间件：

- `GET /api/v1/admin/overview`：全站概览
- `GET /api/v1/admin/users`：用户列表
- `PATCH /api/v1/admin/users/{id}`：角色、状态、存储配额
- `GET /api/v1/admin/images`：全站图片列表
- `DELETE /api/v1/admin/images/{id}`：删除图片
- `GET|PUT /api/v1/admin/api-settings`：开放 API 全局策略
- `GET /api/v1/admin/api-keys`：全站密钥列表
- `PATCH /api/v1/admin/api-keys/{id}`：单独调整密钥限流和额度

## Axios 接入示例

```js
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  timeout: 15000,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export default api
```

上传时直接提交 `FormData`，不要手动设置 multipart boundary：

```js
const form = new FormData()
form.append('file', file)
form.append('album_id', albumId)
const { data } = await api.post('/images', form)
```
