<?php

namespace app\model;

use think\Model;

class UserDbModel extends Model
{
    protected $table = 'users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $hidden = ['password_hash'];
    protected $schema = [
        'id' => 'integer',
        'username' => 'string',
        'email' => 'string',
        'password_hash' => 'string',
        'role' => 'string',
        'status' => 'integer',
        'storage_quota' => 'integer',
        'storage_used' => 'integer',
        'last_login_at' => 'datetime',
        'last_login_ip' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function findByIdentifier(string $identifier)
    {
        return static::where('username', $identifier)
            ->whereOr('email', $identifier)
            ->find();
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, (string) $this->password_hash);
    }

    public function canStore(int $bytes): bool
    {
        return $bytes > 0
            && (int) $this->storage_used + $bytes <= (int) $this->storage_quota;
    }

    public function getPermissions(): array
    {
        return (array) config('permission.roles.' . $this->role . '.permissions', []);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function getRoleLabel(): string
    {
        return (string) config(
            'permission.roles.' . $this->role . '.label',
            (string) $this->role
        );
    }

    public function tokens()
    {
        return $this->hasMany(UserTokenDbModel::class, 'user_id', 'id');
    }

    public function albums()
    {
        return $this->hasMany(AlbumDbModel::class, 'user_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ImageDbModel::class, 'user_id', 'id');
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKeyDbModel::class, 'user_id', 'id');
    }
}
