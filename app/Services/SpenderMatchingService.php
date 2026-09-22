<?php

namespace App\Services;

use App\Models\Spender;

class SpenderMatchingService
{
    /**
     * Match a normalised import row to an existing donor.
     *
     * @param array $zeile Normalised row with 'nachname', 'firma', 'plz' etc.
     * @return array{spender: Spender|null, konfidenz: float, empfehlung: string}
     */
    public function finde(array $zeile): array
    {
        $name = $zeile['firma'] ?: $zeile['nachname'];
        $plz  = $zeile['plz'] ?? '';

        if ($name === '') {
            return ['spender' => null, 'konfidenz' => 0.0, 'empfehlung' => 'neu_anlegen'];
        }

        $suchKey = Spender::normalisiereMatchkey($name, $plz);

        // Load all active donors and try to match
        // For 1750 donors this is acceptable; for much larger sets use DB-level search
        $spender = Spender::withTrashed(false)->get();

        $bestMatch     = null;
        $bestKonfidenz = 0.0;

        foreach ($spender as $s) {
            $kandidatKey = $s->normalisierter_matchkey;
            $konfidenz   = $this->berechneKonfidenz($suchKey, $kandidatKey);

            if ($konfidenz > $bestKonfidenz) {
                $bestKonfidenz = $konfidenz;
                $bestMatch     = $s;
            }
        }

        $empfehlung = match(true) {
            $bestKonfidenz >= 0.9  => 'verwenden',
            $bestKonfidenz >= 0.65 => 'pruefen',
            default                => 'neu_anlegen',
        };

        if ($bestKonfidenz < 0.4) {
            $bestMatch = null;
        }

        return [
            'spender'    => $bestMatch,
            'konfidenz'  => $bestKonfidenz,
            'empfehlung' => $empfehlung,
        ];
    }

    private function berechneKonfidenz(string $suchKey, string $kandidatKey): float
    {
        if ($suchKey === $kandidatKey) {
            return 1.0;
        }

        // Split into name_plz parts
        [$suchName, $suchPlz]      = $this->splitKey($suchKey);
        [$kandidatName, $kandidatPlz] = $this->splitKey($kandidatKey);

        // PLZ must match exactly for a high-confidence hit
        $plzMatch = ($suchPlz !== '' && $suchPlz === $kandidatPlz);

        $distanz = levenshtein($suchName, $kandidatName);
        $maxLen  = max(strlen($suchName), strlen($kandidatName));

        if ($maxLen === 0) {
            return 0.0;
        }

        $nameSimilarity = 1 - ($distanz / $maxLen);
        $threshold = config('spendenquittung.matching_fuzzy_threshold', 2);

        if ($distanz === 0 && $plzMatch) {
            return 1.0;
        }

        if ($distanz <= $threshold && $plzMatch) {
            return 0.85;
        }

        if ($distanz === 0 && ! $plzMatch) {
            return 0.7; // same name, different PLZ – might be relocation
        }

        if ($distanz <= $threshold && ! $plzMatch) {
            return 0.5;
        }

        // Name similarity without threshold
        if ($plzMatch) {
            return $nameSimilarity * 0.8;
        }

        return $nameSimilarity * 0.4;
    }

    private function splitKey(string $key): array
    {
        $parts = explode('_', $key, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
