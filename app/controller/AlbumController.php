<?php

namespace app\controller;

use app\model\AlbumDbModel;
use app\model\ImageDbModel;
use app\Request;
use think\facade\Db;
use think\facade\Log;
use Throwable;

class AlbumController
{
    public function index(Request $request)
    {
        $albums = AlbumDbModel::where('user_id', $request->user->id)
            ->order('id', 'desc')
            ->select();

        $list = [];
        foreach ($albums as $album) {
            $item = $album->toArray();
            $item['image_count'] = ImageDbModel::where('user_id', $request->user->id)
                ->where('album_id', $album->id)
                ->count();
            $list[] = $item;
        }

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => ['list' => $list],
        ]);
    }

    public function create(Request $request)
    {
        $param = $request->param();
        $name = trim((string) ($param['name'] ?? ''));
        $description = trim((string) ($param['description'] ?? ''));

        if ($name === '' || mb_strlen($name) > 80) {
            return json([
                'code' => 400,
                'message' => '相册名称不能为空且不能超过80个字符',
                'data' => null,
            ], 400);
        }

        if (mb_strlen($description) > 500) {
            return json([
                'code' => 400,
                'message' => '相册说明不能超过500个字符',
                'data' => null,
            ], 400);
        }

        if (AlbumDbModel::where('user_id', $request->user->id)->where('name', $name)->find()) {
            return json([
                'code' => 409,
                'message' => '同名相册已存在',
                'data' => null,
            ], 409);
        }

        try {
            $album = AlbumDbModel::create([
                'user_id' => $request->user->id,
                'name' => $name,
                'description' => $description ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::error('创建相册失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '创建相册失败',
                'data' => null,
            ], 500);
        }

        $data = $album->toArray();
        $data['image_count'] = 0;

        return json([
            'code' => 200,
            'message' => '创建成功',
            'data' => $data,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $album = AlbumDbModel::where('id', $id)
            ->where('user_id', $request->user->id)
            ->find();

        if (!$album) {
            return json([
                'code' => 404,
                'message' => '相册不存在',
                'data' => null,
            ], 404);
        }

        $param = $request->param();
        $data = [];

        if (array_key_exists('name', $param)) {
            $name = trim((string) $param['name']);
            if ($name === '' || mb_strlen($name) > 80) {
                return json([
                    'code' => 400,
                    'message' => '相册名称不能为空且不能超过80个字符',
                    'data' => null,
                ], 400);
            }

            $exists = AlbumDbModel::where('user_id', $request->user->id)
                ->where('name', $name)
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                return json([
                    'code' => 409,
                    'message' => '同名相册已存在',
                    'data' => null,
                ], 409);
            }
            $data['name'] = $name;
        }

        if (array_key_exists('description', $param)) {
            $description = trim((string) $param['description']);
            if (mb_strlen($description) > 500) {
                return json([
                    'code' => 400,
                    'message' => '相册说明不能超过500个字符',
                    'data' => null,
                ], 400);
            }
            $data['description'] = $description ?: null;
        }

        if ($data !== []) {
            $album->save($data);
        }

        $result = $album->toArray();
        $result['image_count'] = ImageDbModel::where('album_id', $album->id)->count();

        return json([
            'code' => 200,
            'message' => '保存成功',
            'data' => $result,
        ]);
    }

    public function delete(Request $request, int $id)
    {
        $album = AlbumDbModel::where('id', $id)
            ->where('user_id', $request->user->id)
            ->find();

        if (!$album) {
            return json([
                'code' => 404,
                'message' => '相册不存在',
                'data' => null,
            ], 404);
        }

        try {
            Db::transaction(function () use ($album, $request) {
                ImageDbModel::where('user_id', $request->user->id)
                    ->where('album_id', $album->id)
                    ->update(['album_id' => null]);
                $album->delete();
            });
        } catch (Throwable $exception) {
            Log::error('删除相册失败：' . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => '删除相册失败',
                'data' => null,
            ], 500);
        }

        return json([
            'code' => 200,
            'message' => '相册已删除，图片已移至未分类',
            'data' => null,
        ]);
    }
}
