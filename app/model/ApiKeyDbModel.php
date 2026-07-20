<?php

namespace app\model;

use think\Model;

class ApiKeyDbModel extends Model
{
    protected $table = 'api_keys';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $hidden = ['key_hash'];
    protected $append = ['total_remaining'];
    protected $schema = [
        'id' => 'integer',
        'user_id' => 'integer',
        'name' => 'string',
        'key_prefix' => 'string',
        'key_hash' => 'string',
        'status' => 'integer',
        'rate_limit' => 'integer',
        'rate_window' => 'integer',
        'total_limit' => 'integer',
        'used_count' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 原始密钥只在创建或重置时返回。
     */
    public static function issue(
        int $userId,
        string $name,
        int $rateLimit,
        int $rateWindow,
        int $totalLimit,
        ?string $expiresAt
    ): array {
        $plainKey = 'tb_' . bin2hex(random_bytes(24));
        $model = static::create([
            'user_id' => $userId,
            'name' => mb_substr($name, 0, 60),
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => hash('sha256', $plainKey),
            'status' => 1,
            'rate_limit' => max(1, $rateLimit),
            'rate_window' => max(1, $rateWindow),
            'total_limit' => max(0, $totalLimit),
            'used_count' => 0,
            'expires_at' => $expiresAt,
        ]);

        return ['key' => $plainKey, 'model' => $model];
    }

    public static function findValid(string $plainKey)
    {
        if (!str_starts_with($plainKey, 'tb_')) {
            return null;
        }

        return static::where('key_hash', hash('sha256', $plainKey))
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->whereOr('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->find();
    }

    public function getTotalRemainingAttr($value, array $data)
    {
        $limit = (int) ($data['total_limit'] ?? 0);
        if ($limit === 0) {
            return null;
        }

        return max(0, $limit - (int) ($data['used_count'] ?? 0));
    }

    public function user()
    {
        return $this->belongsTo(UserDbModel::class, 'user_id', 'id');
    }
}
