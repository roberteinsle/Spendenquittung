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
        // Cache the plain value rather than the model: a serialised Eloquent
        // object in the cache turns into an incomplete class as soon as the
        // class definition changes, e.g. after a deploy.
        $cached = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            $setting = static::find($key);

            return $setting
                ? ['value' => $setting->value, 'type' => $setting->type]
                : null;
        });

        if ($cached === null) {
            return $default;
        }

        return match($cached['type']) {
            'boolean' => (bool) $cached['value'],
            'json'    => json_decode($cached['value'], true),
            default   => $cached['value'],
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
