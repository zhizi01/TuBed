<?php

namespace app\controller;

use app\model\AlbumDbModel;
use app\model\ImageDbModel;
use app\model\UserDbModel;
use app\Request;
use RuntimeException;
use think\facade\Db;
use think\facade\Filesystem;
use think\facade\Log;
use Throwable;

class ImageController
{
    public function index(Request $request)
    {
        $param = $request->param();
        $page = max(1, (int) ($param['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($param['page_size'] ?? 24)));

        $query = ImageDbModel::where('user_id', $request->user->id);

        if (array_key_exists('album_id', $param) && $param['album_id'] !== '') {
            $albumId = (int) $param['album_id'];
            $albumId > 0
                ? $query->where('album_id', $albumId)
                : $query->whereNull('album_id');
        }

        $keyword = trim((string) ($param['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('original_name|title', 'like', '%' . $keyword . '%');
        }

        $mimeType = trim((string) ($param['mime_type'] ?? ''));
        if ($mimeType !== '') {
            $query->where('mime_type', $mimeType);
        }

        $total = (clone $query)->count();
        $list = $query->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $file = $request->file('file');
        if (!$file || is_array($file)) {
            return json([
                'code' => 400,
                'message' => '请选择一张图片上传',
                'data' => null,
            ], 400);
        }

        if (!$file->isValid()) {
            return json([
                'code' => 400,
                'message' => '上传文件无效',
                'data' => null,
            ], 400);
        }

        $fileSize = (int) $file->getSize();
        $maxSize = (int) config('upload.max_size', 20 * 1024 * 1024);
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            return json([
                'code' => 413,
                'message' => '图片大小不能超过' . $this->formatBytes($maxSize),
                'data' => null,
            ], 413);
        }

        $mimeType = $file->getMime();
        $allowedMimes = (array) config('upload.allowed_mimes', []);
        if (!isset($allowedMimes[$mimeType])) {
            return json([
                'code' => 415,
                'message' => '仅支持 JPG、PNG、GIF、WebP 和 AVIF 图片',
                'data' => null,
            ], 415);
        }

        $imageInfo = @getimagesize($file->getPathname());
        if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $mimeType) {
            return json([
                'code' => 415,
                'message' => '文件内容不是有效图片',
                'data' => null,
            ], 415);
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width <= 0 || $height <= 0
            || $width * $height > (int) config('upload.max_pixels', 100000000)) {
            return json([
                'code' => 422,
                'message' => '图片像素尺寸过大',
                'data' => null,
            ], 422);
        }

        $param = $request->param();
        $title = trim((string) ($param['title'] ?? ''));
        if (mb_strlen($title) > 100) {
            return json([
                'code' => 400,
                'message' => '图片标题不能超过100个字符',
                'data' => null,
            ], 400);
        }

        $albumId = null;
        if (isset($param['album_id']) && (int) $param['album_id'] > 0) {
            $albumId = (int) $param['album_id'];
            if (!$this->findOwnedAlbum($albumId, (int) $request->user->id)) {
                return json([
                    'code' => 404,
                    'message' => '相册不存在',
                    'data' => null,
                ], 404);
            }
        }

        if (!$request->user->canStore($fileSize)) {
            return json([
                'code' => 413,
                'message' => '存储空间不足',
                'data' => null,
            ], 413);
        }

        $publicId = bin2hex(random_bytes(16));
        $extension = $allowedMimes[$mimeType];
        $diskName = (string) config('upload.disk', 'public');
        $directory = 'images/' . $request->user->id . '/' . date('Y/m');
        $storagePath = null;

        try {
            $disk = Filesystem::disk($diskName);
            $storagePath = $disk->putFileAs(
                $directory,
                $file,
                $publicId . '.' . $extension
            );

            if ($storagePath === false) {
                throw new RuntimeException('UPLOAD_STORAGE_FAILED');
            }

            $originalName = basename(str_replace('\\', '/', $file->getOriginalName()));
            $originalName = mb_substr($originalName ?: $publicId . '.' . $extension, 0, 255);
            $sha256 = $file->hash('sha256');

            $image = Db::transaction(function () use (
                $request,
                $albumId,
                $title,
                $originalName,
                $diskName,
                $storagePath,
                $mimeType,
                $extension,
                $fileSize,
                $width,
                $height,
                $sha256,
                $publicId
            ) {
                $user = UserDbModel::where('id', $request->user->id)->lock(true)->find();
                if (!$user || !$user->canStore($fileSize)) {
                    throw new RuntimeException('UPLOAD_QUOTA_EXCEEDED');
                }

                if ($albumId && !$this->findOwnedAlbum($albumId, (int) $user->id, true)) {
                    throw new RuntimeException('UPLOAD_ALBUM_NOT_FOUND');
                }

                $image = ImageDbModel::create([
                    'public_id' => $publicId,
                    'user_id' => $user->id,
                    'album_id' => $albumId,
                    'title' => $title ?: null,
                    'original_name' => $originalName,
                    'storage_disk' => $diskName,
                    'storage_path' => $storagePath,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'file_size' => $fileSize,
                    'width' => $width,
                    'height' => $height,
                    'sha256' => $sha256,
                ]);

                $user->save([
                    'storage_used' => (int) $user->storage_used + $fileSize,
                ]);

                return $image;
            });
        } catch (Throwable $exception) {
            if ($storagePath) {
                try {
                    Filesystem::disk($diskName)->delete($storagePath);
                } catch (Throwable $deleteException) {
                    Log::warning('清理上传文件失败：' . $deleteException->getMessage());
                }
            }

            if ($exception->getMessage() === 'UPLOAD_QUOTA_EXCEEDED') {
                return json([
                    'code' => 413,
                    'message' => '存储空间不足',
                    'data' => null,
                ], 413);
            }

            if ($exception->getMessage() === 'UPLOAD_ALBUM_NOT_FOUND') {
                return json([
                    'code' => 404,
                    'message' => '相册不存在',
                    'data' => null,
                ], 404);
            }

            Log::error('图片上传失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '图片保存失败，请稍后重试',
                'data' => null,
            ], 500);
        }

        return json([
            'code' => 200,
            'message' => '上传成功',
            'data' => $image->toArray(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $image = $this->findOwnedImage($id, (int) $request->user->id);
        if (!$image) {
            return json([
                'code' => 404,
                'message' => '图片不存在',
                'data' => null,
            ], 404);
        }

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => $image->toArray(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $image = $this->findOwnedImage($id, (int) $request->user->id);
        if (!$image) {
            return json([
                'code' => 404,
                'message' => '图片不存在',
                'data' => null,
            ], 404);
        }

        $param = $request->param();
        $data = [];

        if (array_key_exists('title', $param)) {
            $title = trim((string) $param['title']);
            if (mb_strlen($title) > 100) {
                return json([
                    'code' => 400,
                    'message' => '图片标题不能超过100个字符',
                    'data' => null,
                ], 400);
            }
            $data['title'] = $title ?: null;
        }

        if (array_key_exists('album_id', $param)) {
            $albumId = (int) ($param['album_id'] ?? 0);
            if ($albumId > 0 && !$this->findOwnedAlbum($albumId, (int) $request->user->id)) {
                return json([
                    'code' => 404,
                    'message' => '相册不存在',
                    'data' => null,
                ], 404);
            }
            $data['album_id'] = $albumId > 0 ? $albumId : null;
        }

        if ($data !== []) {
            $image->save($data);
        }

        return json([
            'code' => 200,
            'message' => '保存成功',
            'data' => $image->toArray(),
        ]);
    }

    public function delete(Request $request, int $id)
    {
        $image = $this->findOwnedImage($id, (int) $request->user->id);
        if (!$image) {
            return json([
                'code' => 404,
                'message' => '图片不存在',
                'data' => null,
            ], 404);
        }

        try {
            [$diskName, $storagePath] = Db::transaction(function () use ($request, $id) {
                $image = ImageDbModel::where('id', $id)
                    ->where('user_id', $request->user->id)
                    ->lock(true)
                    ->find();
                if (!$image) {
                    throw new RuntimeException('IMAGE_NOT_FOUND');
                }

                $user = UserDbModel::where('id', $request->user->id)->lock(true)->find();
                $diskName = (string) $image->storage_disk;
                $storagePath = (string) $image->storage_path;
                $fileSize = (int) $image->file_size;

                $image->delete();
                $user->save([
                    'storage_used' => max(0, (int) $user->storage_used - $fileSize),
                ]);

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

            Log::error('删除图片失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '删除图片失败',
                'data' => null,
            ], 500);
        }

        try {
            Filesystem::disk($diskName)->delete($storagePath);
        } catch (Throwable $exception) {
            // 数据已删除，遗留文件可由后续清理任务处理。
            Log::warning('删除存储文件失败：' . $exception->getMessage());
        }

        return json([
            'code' => 200,
            'message' => '删除成功',
            'data' => null,
        ]);
    }

    private function findOwnedAlbum(int $albumId, int $userId, bool $lock = false)
    {
        $query = AlbumDbModel::where('id', $albumId)->where('user_id', $userId);

        return $lock ? $query->lock(true)->find() : $query->find();
    }

    private function findOwnedImage(int $imageId, int $userId)
    {
        return ImageDbModel::where('id', $imageId)
            ->where('user_id', $userId)
            ->find();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
