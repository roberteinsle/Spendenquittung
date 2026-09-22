<?php

namespace App\Services;

use InvalidArgumentException;

class BetragInWortenService
{
    private const EINHEITEN = [
        '', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
        'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
        'siebzehn', 'achtzehn', 'neunzehn',
    ];

    private const ZEHNER = [
        '', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig',
    ];

    /**
     * Convert a Euro amount to German words.
     * e.g. 70.70  → "siebzig Euro und siebzig Cent"
     *      150.00 → "einhundertfünfzig Euro"
     *      1.00   → "ein Euro"
     */
    public function konvertiere(float|int|string $betrag): string
    {
        $betrag = $this->normalisiereEingabe((string) $betrag);

        if ($betrag < 0 || $betrag > 99999.99) {
            throw new InvalidArgumentException("Betrag muss zwischen 0 und 99.999,99 € liegen.");
        }

        $euro  = (int) $betrag;
        $cent  = (int) round(($betrag - $euro) * 100);

        $euroText = $this->zahlZuWorten($euro);
        $centText = $cent > 0 ? $this->zahlZuWorten($cent) : null;

        $result = ucfirst($euroText) . ($euro === 1 ? ' Euro' : ' Euro');

        if ($centText !== null) {
            $result .= ' und ' . $centText . ($cent === 1 ? ' Cent' : ' Cent');
        }

        return $result;
    }

    /**
     * Normalise German/international number input to a float-compatible string.
     * Handles: "1.250,70", "1250.70", "1250,70", "70,70"
     */
    public function normalisiereEingabe(string $input): float
    {
        $input = trim($input);
        $input = str_replace(['€', ' '], '', $input);

        // If both . and , present: "1.250,70" → remove . as thousands separator
        if (str_contains($input, '.') && str_contains($input, ',')) {
            $input = str_replace('.', '', $input);
            $input = str_replace(',', '.', $input);
        } elseif (str_contains($input, ',')) {
            // German decimal comma without thousands separator
            $input = str_replace(',', '.', $input);
        }
        // Otherwise: standard float string with dot

        return (float) $input;
    }

    private function zahlZuWorten(int $n): string
    {
        if ($n === 0) {
            return 'null';
        }

        if ($n < 0) {
            return 'minus ' . $this->zahlZuWorten(-$n);
        }

        $result = '';

        if ($n >= 1000) {
            $tausender = (int) ($n / 1000);
            $result   .= ($tausender === 1 ? 'ein' : $this->zahlZuWorten($tausender)) . 'tausend';
            $n        %= 1000;
        }

        if ($n >= 100) {
            $hundert = (int) ($n / 100);
            $result .= self::EINHEITEN[$hundert] . 'hundert';
            $n      %= 100;
        }

        if ($n >= 20) {
            $z = (int) ($n / 10);
            $e = $n % 10;
            if ($e > 0) {
                $result .= self::EINHEITEN[$e] . 'und' . self::ZEHNER[$z];
            } else {
                $result .= self::ZEHNER[$z];
            }
        } elseif ($n > 0) {
            $result .= self::EINHEITEN[$n];
        }

        return $result;
    }
}
