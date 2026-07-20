<?php

namespace app\model;

use think\Model;

class SystemSettingDbModel extends Model
{
    protected $table = 'system_settings';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id' => 'integer',
        'setting_key' => 'string',
        'setting_value' => 'string',
        'value_type' => 'string',
        'description' => 'string',
        'updated_at' => 'datetime',
    ];

    public static function valueOf(string $key, $default = null)
    {
        $setting = static::where('setting_key', $key)->find();
        if (!$setting) {
            return $default;
        }

        return match ($setting->value_type) {
            'boolean' => in_array(strtolower($setting->setting_value), ['1', 'true', 'yes'], true),
            'integer' => (int) $setting->setting_value,
            'json' => json_decode($setting->setting_value, true) ?? $default,
            default => $setting->setting_value,
        };
    }

    public static function saveValue(string $key, $value, string $type = 'string'): void
    {
        $setting = static::where('setting_key', $key)->find();
        $storedValue = $type === 'json'
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) ($type === 'boolean' ? (int) (bool) $value : $value);

        if ($setting) {
            $setting->save([
                'setting_value' => $storedValue,
                'value_type' => $type,
            ]);
            return;
        }

        static::create([
            'setting_key' => $key,
            'setting_value' => $storedValue,
            'value_type' => $type,
        ]);
    }
}
