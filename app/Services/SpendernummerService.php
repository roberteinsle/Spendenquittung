<?php

namespace App\Services;

use App\Models\Spender;
use RuntimeException;

class SpendernummerService
{
    private const MAX_ATTEMPTS = 100;

    /**
     * Generate a unique Spendernummer in the format 8xxxx (e.g. "81234").
     */
    public function generiere(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $suffix   = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $kandidat = '8' . $suffix;

            if (! Spender::withTrashed()->where('spendernummer', $kandidat)->exists()) {
                return $kandidat;
            }
        }

        throw new RuntimeException(
            "Konnte nach " . self::MAX_ATTEMPTS . " Versuchen keine eindeutige Spendernummer generieren."
        );
    }
}
