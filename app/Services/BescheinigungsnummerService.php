<?php

namespace App\Services;

use App\Models\Spende;
use RuntimeException;

class BescheinigungsnummerService
{
    private const MAX_ATTEMPTS = 100;

    /**
     * Generate a unique Bescheinigungsnummer in the format YYxxxx (e.g. "264711").
     * Year defaults to current year if not specified.
     */
    public function generiere(?int $jahr = null): string
    {
        $jahr ??= (int) date('Y');
        $prefix = substr((string) $jahr, -2); // last 2 digits

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $suffix    = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $kandidat  = $prefix . $suffix;

            if (! Spende::withTrashed()->where('bescheinigungsnummer', $kandidat)->exists()) {
                return $kandidat;
            }
        }

        throw new RuntimeException(
            "Konnte nach " . self::MAX_ATTEMPTS . " Versuchen keine eindeutige Bescheinigungsnummer generieren."
        );
    }
}
