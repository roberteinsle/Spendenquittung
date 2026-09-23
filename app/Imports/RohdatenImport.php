<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Minimal import stub. We deliberately do NOT use WithHeadingRow, because it
 * slugs the header cells ("Spende vom" → "spende_vom", "Straße" → "strasse")
 * and ImportNormalisierungService matches against the original German headers.
 * The header row is mapped by ImportParserService instead.
 */
class RohdatenImport implements ToArray
{
    /** @var array<int, array<int, mixed>> */
    public array $rows = [];

    /**
     * @param array<int, array<int, mixed>> $array
     */
    public function array(array $array): void
    {
        $this->rows = $array;
    }
}
