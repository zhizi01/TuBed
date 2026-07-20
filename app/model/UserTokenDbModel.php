<?php

namespace app\model;

use think\Model;

class UserTokenDbModel extends Model
{
    protected $table = 'user_tokens';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    protected $hidden = ['token_hash'];
    protected $schema = [
        'id' => 'integer',
        'user_id' => 'integer',
        'name' => 'string',
        'token_hash' => 'string',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * 原始令牌只在签发时返回，数据库仅保存摘要。
     */
    public static function issue(int $userId, string $name = 'web', int $ttlDays = 30): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $model = static::create([
            'user_id' => $userId,
            'name' => mb_substr($name ?: 'web', 0, 60),
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $ttlDays) * 86400),
        ]);

        return ['token' => $plainToken, 'model' => $model];
    }

    public static function findValid(string $plainToken)
    {
        if ($plainToken === '') {
            return null;
        }

        return static::where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();
    }

    public function user()
    {
        return $this->belongsTo(UserDbModel::class, 'user_id', 'id');
    }
}
