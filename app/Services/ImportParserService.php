<?php

namespace App\Services;

use App\Imports\RohdatenImport;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Turns an uploaded spreadsheet or a block of pasted clipboard text into a list
 * of rows keyed by the original header cells. Normalisation of the values is
 * the job of ImportNormalisierungService.
 */
class ImportParserService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function ausDatei(string $absoluterPfad): array
    {
        if (! is_readable($absoluterPfad)) {
            throw new RuntimeException('Die hochgeladene Datei konnte nicht gelesen werden.');
        }

        $tabellen = Excel::toArray(new RohdatenImport, $absoluterPfad);

        return $this->mitKopfzeile($tabellen[0] ?? []);
    }

    /**
     * Parse tab-, semicolon- or comma-delimited text, as produced by copying a
     * block of cells out of Excel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ausText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        if ($text === '') {
            return [];
        }

        $zeilen    = explode("\n", $text);
        $trenner   = $this->erkenneTrenner($zeilen[0]);
        $rohzeilen = array_map(
            fn (string $zeile) => array_map(trim(...), explode($trenner, $zeile)),
            $zeilen,
        );

        return $this->mitKopfzeile($rohzeilen);
    }

    private function erkenneTrenner(string $kopfzeile): string
    {
        foreach (["\t", ';', ','] as $kandidat) {
            if (str_contains($kopfzeile, $kandidat)) {
                return $kandidat;
            }
        }

        return "\t";
    }

    /**
     * Use the first non-empty row as the header and key every following row by it.
     *
     * @param array<int, array<int, mixed>> $rohzeilen
     * @return array<int, array<string, mixed>>
     */
    private function mitKopfzeile(array $rohzeilen): array
    {
        $kopf       = null;
        $ergebnis   = [];
        $quellZeile = 0;

        foreach ($rohzeilen as $zeile) {
            $quellZeile++;

            if (! is_array($zeile) || $this->istLeer($zeile)) {
                continue;
            }

            if ($kopf === null) {
                $kopf = $this->kopfSpalten($zeile);

                continue;
            }

            $datensatz = [];
            foreach ($kopf as $spalte => $name) {
                $datensatz[$name] = $zeile[$spalte] ?? null;
            }

            // Keep the spreadsheet row number so the preview can point at the source.
            $datensatz['__zeile'] = $quellZeile;

            $ergebnis[] = $datensatz;
        }

        return $ergebnis;
    }

    /**
     * Build column index → header name, falling back to the spreadsheet letter
     * for empty header cells so that the note columns L, M and N still map.
     *
     * @param array<int, mixed> $zeile
     * @return array<int, string>
     */
    private function kopfSpalten(array $zeile): array
    {
        $kopf = [];

        foreach (array_values($zeile) as $index => $wert) {
            $name = trim((string) $wert);

            if ($name === '') {
                $name = $this->spaltenBuchstabe($index);
            }

            // Later duplicates must not overwrite the first occurrence.
            if (in_array($name, $kopf, true)) {
                $name .= '_' . $index;
            }

            $kopf[$index] = $name;
        }

        return $kopf;
    }

    private function spaltenBuchstabe(int $index): string
    {
        $buchstabe = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $buchstabe = chr(65 + ($i % 26)) . $buchstabe;
        }

        return $buchstabe;
    }

    /**
     * @param array<int, mixed> $zeile
     */
    private function istLeer(array $zeile): bool
    {
        foreach ($zeile as $wert) {
            if ($wert !== null && trim((string) $wert) !== '') {
                return false;
            }
        }

        return true;
    }
}
