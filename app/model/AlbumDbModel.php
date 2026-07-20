<?php

namespace app\model;

use think\Model;

class AlbumDbModel extends Model
{
    protected $table = 'albums';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UserDbModel::class, 'user_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ImageDbModel::class, 'album_id', 'id');
    }
}
