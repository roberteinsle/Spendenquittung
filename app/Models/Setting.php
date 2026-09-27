<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['key', 'value', 'type'];

    /**
     * Schlüssel, deren Wert verschlüsselt abgelegt wird. Nur der Cache und die
     * Datenbank sehen den Chiffretext – ausgelesen wird er entschlüsselt.
     */
    public const GEHEIM = [
        'mail_passwort',
    ];

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
            'boolean'   => (bool) $cached['value'],
            'json'      => json_decode($cached['value'], true),
            // Im Cache liegt der Chiffretext, entschlüsselt wird erst hier.
            'encrypted' => static::entschluessele($cached['value']) ?? $default,
            default     => $cached['value'],
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $type = match(true) {
            in_array($key, static::GEHEIM, true) => 'encrypted',
            is_bool($value)                      => 'boolean',
            is_array($value)                     => 'json',
            default                              => 'string',
        };

        $stored = match($type) {
            'encrypted' => Crypt::encryptString((string) $value),
            'json'      => json_encode($value),
            default     => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );

        Cache::forget("setting_{$key}");
    }

    /**
     * Ein alter, noch unverschlüsselter Wert oder ein Schlüsselwechsel darf die
     * Seite nicht zerlegen – dann gilt der Wert schlicht als nicht gesetzt.
     */
    private static function entschluessele(?string $wert): ?string
    {
        if (blank($wert)) {
            return null;
        }

        try {
            return Crypt::decryptString($wert);
        } catch (DecryptException) {
            return null;
        }
    }
}
