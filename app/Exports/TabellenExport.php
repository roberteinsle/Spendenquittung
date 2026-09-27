<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Schlanke Excel-Vorlage: Überschriften plus Zeilen, sonst nichts.
 */
class TabellenExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, string>  $ueberschriften
     * @param  array<int, array<int, mixed>>  $zeilen
     */
    public function __construct(
        private array $ueberschriften,
        private array $zeilen,
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->ueberschriften;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->zeilen;
    }
}
