<?php

namespace App\Observers;

use App\Models\Spende;
use App\Services\BescheinigungsnummerService;
use App\Services\BetragInWortenService;

class SpendeObserver
{
    public function creating(Spende $spende): void
    {
        if (empty($spende->bescheinigungsnummer)) {
            $jahr = $spende->spendendatum
                ? $spende->spendendatum->year
                : (int) date('Y');

            $spende->bescheinigungsnummer = app(BescheinigungsnummerService::class)->generiere($jahr);
        }

        if (empty($spende->betrag_in_worten) && $spende->betrag !== null) {
            $spende->betrag_in_worten = app(BetragInWortenService::class)->konvertiere($spende->betrag);
        }

        if (empty($spende->ausstellungsdatum)) {
            $spende->ausstellungsdatum = now()->toDateString();
        }
    }
}
