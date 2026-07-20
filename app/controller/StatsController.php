<?php

namespace app\controller;

use app\model\AlbumDbModel;
use app\model\ImageDbModel;
use app\Request;
use think\facade\Db;

class StatsController
{
    public function overview(Request $request)
    {
        $userId = (int) $request->user->id;
        $imageCount = ImageDbModel::where('user_id', $userId)->count();
        $albumCount = AlbumDbModel::where('user_id', $userId)->count();
        $recentCount = ImageDbModel::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 7 * 86400))
            ->count();

        $byType = Db::name('images')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->field('extension, COUNT(*) AS image_count, COALESCE(SUM(file_size), 0) AS total_size')
            ->group('extension')
            ->order('image_count', 'desc')
            ->select()
            ->toArray();

        $used = (int) $request->user->storage_used;
        $quota = (int) $request->user->storage_quota;

        return json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'image_count' => $imageCount,
                'album_count' => $albumCount,
                'recent_7_days' => $recentCount,
                'storage' => [
                    'used' => $used,
                    'quota' => $quota,
                    'remaining' => max(0, $quota - $used),
                    'percent' => $quota > 0 ? round($used * 100 / $quota, 2) : 0,
                ],
                'by_type' => $byType,
            ],
        ]);
    }
}
