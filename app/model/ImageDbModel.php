<?php

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class ImageDbModel extends Model
{
    use SoftDelete;

    protected $table = 'images';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = 'deleted_at';
    protected $defaultSoftDelete = null;
    protected $append = ['url'];
    protected $hidden = ['user_id', 'storage_disk', 'storage_path', 'deleted_at'];
    protected $schema = [
        'id' => 'integer',
        'public_id' => 'string',
        'user_id' => 'integer',
        'album_id' => 'integer',
        'title' => 'string',
        'original_name' => 'string',
        'storage_disk' => 'string',
        'storage_path' => 'string',
        'mime_type' => 'string',
        'extension' => 'string',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sha256' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getUrlAttr($value, array $data): string
    {
        $baseUrl = rtrim((string) config('filesystem.disks.public.url', '/storage'), '/');
        $path = str_replace('\\', '/', (string) ($data['storage_path'] ?? ''));

        return $baseUrl . '/' . ltrim($path, '/');
    }

    public function user()
    {
        return $this->belongsTo(UserDbModel::class, 'user_id', 'id');
    }

    public function album()
    {
        return $this->belongsTo(AlbumDbModel::class, 'album_id', 'id');
    }
}
