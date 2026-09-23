<?php

namespace App\Services;

use App\Enums\ImportAktion;
use App\Models\Spende;

/**
 * Turns parsed raw rows into the proposal list the import wizard shows in its
 * preview step: normalised values, a matched donor and a suggested action.
 *
 * Nothing here touches the database beyond reading, so the preview can be
 * recomputed as often as needed.
 */
class ImportVorbereitungService
{
    public function __construct(
        private ImportNormalisierungService $normalisierung,
        private SpenderMatchingService $matching,
        private BetragInWortenService $betragInWorten,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $rohzeilen
     * @return array<int, array<string, mixed>>
     */
    public function bereiteVor(array $rohzeilen): array
    {
        $vorschlaege = [];

        foreach ($rohzeilen as $roh) {
            $zeilenNr = (int) ($roh['__zeile'] ?? 0);
            $zeile    = $this->normalisierung->normalisiereZeile($roh);

            if ($zeile === null) {
                continue;
            }

            $vorschlaege[] = $this->zuVorschlag($zeile, $zeilenNr);
        }

        return $vorschlaege;
    }

    /**
     * @param array<string, mixed> $zeile
     * @return array<string, mixed>
     */
    private function zuVorschlag(array $zeile, int $zeilenNr): array
    {
        $treffer = $this->matching->finde($zeile);
        $spender = $treffer['spender'];

        $aktion = match ($treffer['empfehlung']) {
            'verwenden', 'pruefen' => ImportAktion::Verwenden,
            default                => ImportAktion::NeuAnlegen,
        };

        if ($spender === null) {
            $aktion = ImportAktion::NeuAnlegen;
        }

        $hinweise = [];

        if ($treffer['empfehlung'] === 'pruefen') {
            $hinweise[] = 'Zuordnung unsicher (' . round($treffer['konfidenz'] * 100) . ' %) – bitte prüfen';
        }

        $betrag = $zeile['betrag'];

        if ($betrag === null || $betrag <= 0) {
            $hinweise[] = 'Kein gültiger Betrag';
            $aktion     = ImportAktion::Ueberspringen;
        }

        if ($zeile['spendendatum'] === null) {
            $hinweise[] = 'Kein gültiges Spendendatum';
            $aktion     = ImportAktion::Ueberspringen;
        }

        if ($zeile['nachname'] === '' && ($zeile['firma'] ?? '') === '') {
            $hinweise[] = 'Kein Name';
            $aktion     = ImportAktion::Ueberspringen;
        }

        if ($this->istDublette($zeile, $spender?->id)) {
            $hinweise[] = 'Bereits erfasst';
            $aktion     = ImportAktion::Ueberspringen;
        }

        return [
            'zeile_nr'         => $zeilenNr,
            'aktion'           => $aktion->value,
            'spender_id'       => $spender?->id,
            'konfidenz'        => round($treffer['konfidenz'], 2),
            'treffer_name'     => $spender?->vollname,
            'hinweis'          => implode(' · ', $hinweise),
            'anrede'           => $zeile['anrede'],
            'vorname'          => $zeile['vorname'],
            'nachname'         => $zeile['nachname'],
            'firma'            => $zeile['firma'],
            'strasse'          => $zeile['strasse'],
            'plz'              => $zeile['plz'],
            'ort'              => $zeile['ort'],
            'spendendatum'     => $zeile['spendendatum'],
            'betrag'           => $betrag,
            'betrag_in_worten' => $this->betragInWorten($zeile),
            'anlass'           => $zeile['bemerkung'],
            'alte_lfd_nr'      => $zeile['alte_lfd_nr'],
        ];
    }

    /**
     * @param array<string, mixed> $zeile
     */
    private function betragInWorten(array $zeile): string
    {
        if (! empty($zeile['betrag_in_worten'])) {
            return $zeile['betrag_in_worten'];
        }

        if ($zeile['betrag'] === null || $zeile['betrag'] <= 0) {
            return '';
        }

        try {
            return $this->betragInWorten->konvertiere($zeile['betrag']);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * A row counts as already imported when the same donor received a receipt
     * for the same amount on the same day, or when its old running number has
     * been imported before.
     *
     * @param array<string, mixed> $zeile
     */
    private function istDublette(array $zeile, ?int $spenderId): bool
    {
        if (! empty($zeile['alte_lfd_nr'])
            && Spende::where('alte_lfd_nr', $zeile['alte_lfd_nr'])->exists()) {
            return true;
        }

        if ($spenderId === null || $zeile['spendendatum'] === null || $zeile['betrag'] === null) {
            return false;
        }

        return Spende::where('spender_id', $spenderId)
            ->whereDate('spendendatum', $zeile['spendendatum'])
            ->where('betrag', $zeile['betrag'])
            ->exists();
    }
}
