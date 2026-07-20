<?php

namespace app\model;

use think\Model;

class ApiRateBucketDbModel extends Model
{
    protected $table = 'api_rate_buckets';
    protected $pk = ['api_key_id', 'window_start'];
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'api_key_id' => 'integer',
        'window_start' => 'datetime',
        'request_count' => 'integer',
        'updated_at' => 'datetime',
    ];
}
