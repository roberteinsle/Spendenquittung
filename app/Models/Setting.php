<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['key', 'value', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return static::find($key);
        });

        if (! $setting) {
            return $default;
        }

        return match($setting->type) {
            'boolean' => (bool) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $type = match(true) {
            is_bool($value)  => 'boolean',
            is_array($value) => 'json',
            default          => 'string',
        };

        $stored = is_array($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );

        Cache::forget("setting_{$key}");
    }
}
