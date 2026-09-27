<?php

namespace App\Observers;

use App\Enums\SpendeStatus;
use App\Models\Spende;
use App\Services\BescheinigungsnummerService;
use App\Services\BetragInWortenService;

class SpendeObserver
{
    use SchreibtProtokoll;

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

        if (empty($spende->erstellt_von)) {
            $spende->erstellt_von = auth()->id();
        }

        // The column has a DB default, but that is not reflected on the
        // in-memory model, so set it explicitly.
        if (empty($spende->status)) {
            $spende->status = SpendeStatus::Erfasst;
        }
    }
}
