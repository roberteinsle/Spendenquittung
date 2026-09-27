<?php

namespace App\Services;

use App\Enums\ProtokollAktion;
use App\Enums\SpendeStatus;
use App\Enums\VersandErgebnis;
use App\Enums\VersandKanal;
use App\Models\Spende;
use App\Models\Versandprotokoll;

class VersandprotokollService
{
    /**
     * Record one delivery attempt and advance the Spende status accordingly.
     *
     * @param int|null $benutzerId Who triggered it. Queued jobs run without an
     *                             authenticated user, so they pass this in.
     */
    public function protokolliere(
        Spende $spende,
        VersandKanal $kanal,
        VersandErgebnis $ergebnis = VersandErgebnis::Erfolg,
        ?string $empfaenger = null,
        ?string $nachricht = null,
        ?int $benutzerId = null,
    ): Versandprotokoll {
        $protokoll = $spende->versandprotokolle()->create([
            'kanal'           => $kanal,
            'zeitpunkt'       => now(),
            'empfaenger'      => $empfaenger,
            'ergebnis'        => $ergebnis,
            'nachricht'       => $nachricht,
            'ausgefuehrt_von' => $benutzerId ?? auth()->id(),
        ]);

        if ($ergebnis === VersandErgebnis::Erfolg) {
            $this->setzeStatus($spende, $this->statusFuer($kanal));
        }

        // Zusätzlich in den übergreifenden Prüfpfad. Das Versandprotokoll bleibt
        // die Detailsicht je Bescheinigung, das Protokoll die Gesamtschau.
        app(ProtokollService::class)->schreibe(
            aktion: $this->protokollAktion($kanal, $ergebnis),
            betrifft: $spende,
            beschreibung: $empfaenger ? "An {$empfaenger}" : $nachricht,
            benutzerId: $benutzerId,
        );

        return $protokoll;
    }

    /**
     * Move the Spende forward in the workflow, never backwards.
     */
    public function setzeStatus(Spende $spende, SpendeStatus $neu): void
    {
        if ($neu->stufe() <= ($spende->status?->stufe() ?? -1)) {
            return;
        }

        $spende->update(['status' => $neu]);
    }

    private function protokollAktion(VersandKanal $kanal, VersandErgebnis $ergebnis): ProtokollAktion
    {
        if ($kanal === VersandKanal::Email) {
            return $ergebnis === VersandErgebnis::Erfolg
                ? ProtokollAktion::EmailVersendet
                : ProtokollAktion::EmailFehlgeschlagen;
        }

        return match ($kanal) {
            VersandKanal::Druck => ProtokollAktion::PdfGeoeffnet,
            VersandKanal::Post => ProtokollAktion::PostVersendet,
            default => ProtokollAktion::PdfGeoeffnet,
        };
    }

    private function statusFuer(VersandKanal $kanal): SpendeStatus
    {
        return match($kanal) {
            VersandKanal::Druck => SpendeStatus::Gedruckt,
            VersandKanal::Email,
            VersandKanal::Post  => SpendeStatus::Versendet,
        };
    }
}
