<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when at least one row of a confirmed import could not be written.
 * The surrounding transaction is rolled back, so no receipt numbers are burned.
 */
class ImportFehlgeschlagen extends RuntimeException
{
    /**
     * @param array<int, string> $fehler
     */
    public function __construct(
        public readonly array $fehler,
    ) {
        parent::__construct(
            'Der Import wurde abgebrochen: ' . count($fehler) . ' Zeile(n) konnten nicht gespeichert werden.'
        );
    }
}
