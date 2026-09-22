<?php

namespace App\Services;

use Carbon\Carbon;

class ImportNormalisierungService
{
    private const ANREDE_MAP = [
        'herrn'    => 'Herrn',
        'herr'     => 'Herrn',
        'hern'     => 'Herrn',   // typo in Excel
        'frau'     => 'Frau',
        'firma'    => 'Firma',
        'firma '   => 'Firma',   // trailing space variant
        'eheleute' => 'Eheleute',
        'ehepaar'  => 'Eheleute',
    ];

    /**
     * Normalise a raw Excel row (associative array with German keys) into a clean array.
     *
     * Expected source columns (case-insensitive matching):
     *   An, Besonderheit, Vorname1, Name, Straße, Plz, Ort,
     *   spende vom, Spende, ZIW, lfd. Nr., L (notes), M (notes), N (notes)
     *
     * @param array $row Raw row data
     * @return array|null Normalised row, or null if the row should be skipped
     */
    public function normalisiereZeile(array $row): ?array
    {
        // Normalise keys: lowercase + trim
        $r = [];
        foreach ($row as $k => $v) {
            $r[strtolower(trim((string) $k))] = $v;
        }

        // Map common column aliases
        $nachname    = $this->str($r, ['name', 'nachname']);
        $betragRaw   = $r['spende'] ?? $r['betrag'] ?? null;

        // Skip rows with no name and no amount
        if ($nachname === '' && $betragRaw === null) {
            return null;
        }

        // Parse betrag (skip text values like '/', '?', formula strings)
        $betrag = $this->parseBetrag($betragRaw);

        // Collect notes from columns L, M, N
        $notizen = implode(' | ', array_filter([
            $this->str($r, ['l', 'notiz1', 'bemerkung1']),
            $this->str($r, ['m', 'notiz2', 'bemerkung2']),
            $this->str($r, ['n', 'notiz3', 'bemerkung3']),
        ]));

        $besonderheit = $this->str($r, ['besonderheit', 'anlass']);

        return [
            'anrede'         => $this->normalisiereAnrede($this->str($r, ['an', 'anrede'])),
            'vorname'        => $this->str($r, ['vorname1', 'vorname']),
            'nachname'       => $nachname,
            'firma'          => $this->inferFirma($r),
            'strasse'        => $this->str($r, ['straße', 'strasse', 'str']),
            'plz'            => $this->normalisierePlz($this->str($r, ['plz', 'postleitzahl'])),
            'ort'            => $this->str($r, ['ort', 'stadt']),
            'spendendatum'   => $this->parseDatum($r['spende vom'] ?? $r['datum'] ?? null),
            'betrag'         => $betrag,
            'betrag_in_worten' => $this->str($r, ['ziw', 'betrag_in_worten']),
            'alte_lfd_nr'    => $this->str($r, ['lfd. nr.', 'lfd.nr.', 'lfd nr', 'nr']),
            'bemerkung'      => implode(' | ', array_filter([$besonderheit, $notizen])),
        ];
    }

    private function normalisiereAnrede(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $key = strtolower(trim($raw));

        return self::ANREDE_MAP[$key] ?? null;
    }

    private function inferFirma(array $r): ?string
    {
        $anrede = strtolower(trim((string) ($r['an'] ?? $r['anrede'] ?? '')));
        $besonderheit = $this->str($r, ['besonderheit']);

        // If Anrede is "Firma", the Besonderheit column often holds the company name
        if (in_array($anrede, ['firma', 'firma '], true) && $besonderheit !== '') {
            return $besonderheit;
        }

        return null;
    }

    private function normalisierePlz(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) === 0) {
            return null;
        }

        // German PLZ: 5 digits, zero-pad if shorter (old East German 4-digit PLZ)
        return str_pad($digits, 5, '0', STR_PAD_LEFT);
    }

    public function parseDatum(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // Excel serial date (integer)
        if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
            $serial = (int) $raw;
            if ($serial > 1000 && $serial < 100000) {
                // Excel serial: days since 1900-01-00 (with Lotus 1-2-3 leap year bug)
                $timestamp = ($serial - 25569) * 86400;
                return date('Y-m-d', $timestamp);
            }
        }

        // Float (Excel sometimes stores dates as floats)
        if (is_float($raw)) {
            $serial = (int) $raw;
            $timestamp = ($serial - 25569) * 86400;
            return date('Y-m-d', $timestamp);
        }

        $str = trim((string) $raw);

        // German format: d.m.Y or d.m.y
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $str, $m)) {
            $year = strlen($m[3]) === 2 ? (2000 + (int) $m[3]) : (int) $m[3];
            try {
                return Carbon::createFromDate($year, (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        // Try Carbon generic parse
        try {
            return Carbon::parse($str)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function parseBetrag(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $str = trim((string) $raw);

        // Skip invalid placeholders
        if (in_array($str, ['', '/', '?', '-', 'nein', 'x'], true)) {
            return null;
        }

        // Skip Excel formula artefacts
        if (str_starts_with($str, '=') || str_contains($str, 'SUMME')) {
            return null;
        }

        $str = str_replace(['€', ' '], '', $str);

        // "1.250,70" → 1250.70
        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        $float = filter_var($str, FILTER_VALIDATE_FLOAT);

        return $float === false ? null : $float;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function str(array $r, array $keys): string
    {
        foreach ($keys as $key) {
            $val = $r[$key] ?? null;
            if ($val !== null && trim((string) $val) !== '') {
                return trim((string) $val);
            }
        }
        return '';
    }
}
