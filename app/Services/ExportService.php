<?php

namespace App\Services;

use App\Enums\ExportFormat;
use App\Exports\TabellenExport;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use XMLWriter;

class ExportService
{
    /**
     * Baut den Dateiinhalt aus Spaltenköpfen und Zeilen.
     *
     * @param  array<string, string>  $spalten  Schlüssel => Überschrift
     * @param  array<int, array<string, mixed>>  $zeilen  je Zeile Schlüssel => Wert
     * @param  string  $wurzel  Name des XML-Wurzelelements
     * @param  string  $eintrag  Name eines XML-Eintrags
     */
    public function erzeuge(
        ExportFormat $format,
        array $spalten,
        array $zeilen,
        string $wurzel,
        string $eintrag,
    ): string {
        return match ($format) {
            ExportFormat::Excel => $this->alsExcel($spalten, $zeilen),
            ExportFormat::Markdown => $this->alsMarkdown($spalten, $zeilen),
            ExportFormat::Xml => $this->alsXml($spalten, $zeilen, $wurzel, $eintrag),
        };
    }

    /**
     * @param  array<string, string>  $spalten
     * @param  array<int, array<string, mixed>>  $zeilen
     */
    private function alsExcel(array $spalten, array $zeilen): string
    {
        // Zahlen bleiben Zahlen, damit sich in Excel damit rechnen lässt.
        $daten = array_map(
            fn (array $zeile): array => array_map(
                fn (string $schluessel) => $zeile[$schluessel] ?? null,
                array_keys($spalten),
            ),
            $zeilen,
        );

        return Excel::raw(
            new TabellenExport(array_values($spalten), $daten),
            ExcelWriter::XLSX,
        );
    }

    /**
     * @param  array<string, string>  $spalten
     * @param  array<int, array<string, mixed>>  $zeilen
     */
    private function alsMarkdown(array $spalten, array $zeilen): string
    {
        $zellen = fn (array $werte): string => '| '.implode(' | ', $werte).' |';

        $zeilenText = [
            $zellen(array_map($this->markdownEscape(...), array_values($spalten))),
            $zellen(array_fill(0, count($spalten), '---')),
        ];

        foreach ($zeilen as $zeile) {
            $zeilenText[] = $zellen(array_map(
                fn (string $schluessel): string => $this->markdownEscape(
                    $this->fuerMenschen($zeile[$schluessel] ?? null),
                ),
                array_keys($spalten),
            ));
        }

        return implode("\n", $zeilenText)."\n";
    }

    /**
     * @param  array<string, string>  $spalten
     * @param  array<int, array<string, mixed>>  $zeilen
     */
    private function alsXml(array $spalten, array $zeilen, string $wurzel, string $eintrag): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($wurzel);
        $xml->writeAttribute('erstellt', now()->toIso8601String());
        $xml->writeAttribute('anzahl', (string) count($zeilen));

        foreach ($zeilen as $zeile) {
            $xml->startElement($eintrag);

            foreach (array_keys($spalten) as $schluessel) {
                // writeElement maskiert &, < und > selbst.
                $xml->writeElement($schluessel, $this->fuerMaschinen($zeile[$schluessel] ?? null));
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Deutsche Schreibweise für die lesbaren Formate.
     */
    private function fuerMenschen(mixed $wert): string
    {
        return match (true) {
            $wert === null => '',
            is_bool($wert) => $wert ? 'ja' : 'nein',
            is_float($wert) => number_format($wert, 2, ',', '.'),
            default => (string) $wert,
        };
    }

    /**
     * Punkt als Dezimaltrenner, damit XML maschinell auswertbar bleibt.
     */
    private function fuerMaschinen(mixed $wert): string
    {
        return match (true) {
            $wert === null => '',
            is_bool($wert) => $wert ? 'true' : 'false',
            is_float($wert) => number_format($wert, 2, '.', ''),
            default => (string) $wert,
        };
    }

    /**
     * Ein Pipe-Zeichen im Wert würde die Markdown-Tabelle zerreißen.
     */
    private function markdownEscape(string $wert): string
    {
        return str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], $wert);
    }
}
