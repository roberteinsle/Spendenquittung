<?php

namespace App\Services;

use App\Enums\Anrede;
use App\Enums\ImportAktion;
use App\Exceptions\ImportFehlgeschlagen;
use App\Models\Spende;
use App\Models\Spender;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes the rows confirmed in the import wizard.
 *
 * The whole batch runs in one transaction and is all-or-nothing: if any row
 * fails, everything is rolled back and an ImportFehlgeschlagen lists the
 * offending rows. That matters because every created receipt consumes a
 * Bescheinigungsnummer — a half-finished import would leave gaps in the
 * sequence that cannot be explained to the tax office.
 */
class SpendenImportService
{
    /**
     * @param array<int, array<string, mixed>> $zeilen Rows as confirmed in the wizard
     * @param array{foerderungszweck_id: int|string, ankreuzfeld: string, ausstellungsdatum: string} $vorgaben
     * @return array{erstellt: int, neue_spender: int, uebersprungen: int}
     *
     * @throws ImportFehlgeschlagen
     */
    public function importiere(array $zeilen, array $vorgaben): array
    {
        return DB::transaction(function () use ($zeilen, $vorgaben): array {
            $erstellt      = 0;
            $neueSpender   = 0;
            $uebersprungen = 0;
            $fehler        = [];

            foreach ($zeilen as $zeile) {
                $aktion = ImportAktion::ausWert($zeile['aktion'] ?? null);

                if ($aktion === null || $aktion === ImportAktion::Ueberspringen) {
                    $uebersprungen++;

                    continue;
                }

                try {
                    $spender = $aktion === ImportAktion::NeuAnlegen
                        ? $this->legeSpenderAn($zeile)
                        : Spender::find($zeile['spender_id'] ?? null);

                    if ($spender === null) {
                        throw new RuntimeException('Kein vorhandener Spender zugeordnet.');
                    }

                    if ($aktion === ImportAktion::NeuAnlegen) {
                        $neueSpender++;
                    }

                    $this->legeSpendeAn($zeile, $spender, $vorgaben);
                    $erstellt++;
                } catch (Throwable $e) {
                    $fehler[] = 'Zeile ' . ($zeile['zeile_nr'] ?? '?') . ': ' . $e->getMessage();
                }
            }

            if ($fehler !== []) {
                throw new ImportFehlgeschlagen($fehler);
            }

            return [
                'erstellt'      => $erstellt,
                'neue_spender'  => $neueSpender,
                'uebersprungen' => $uebersprungen,
            ];
        });
    }

    /**
     * @param array<string, mixed> $zeile
     */
    private function legeSpenderAn(array $zeile): Spender
    {
        $nachname = $this->wertOderNull($zeile['nachname'] ?? null);
        $firma    = $this->wertOderNull($zeile['firma'] ?? null);

        if ($nachname === null && $firma === null) {
            throw new RuntimeException('Neuer Spender ohne Name oder Firma.');
        }

        $anrede = $zeile['anrede'] ?? null;

        return Spender::create([
            // The wizard hands over a plain string, a hydrated Anrede, or nothing.
            'anrede'   => $anrede instanceof Anrede ? $anrede : Anrede::tryFrom((string) ($anrede ?? '')),
            'firma'    => $firma,
            'vorname'  => $this->wertOderNull($zeile['vorname'] ?? null),
            'nachname' => $nachname,
            'strasse'  => $this->wertOderNull($zeile['strasse'] ?? null),
            'plz'      => $this->wertOderNull($zeile['plz'] ?? null),
            'ort'      => $this->wertOderNull($zeile['ort'] ?? null),
            'aktiv'    => true,
        ]);
    }

    /**
     * @param array<string, mixed> $zeile
     * @param array<string, mixed> $vorgaben
     */
    private function legeSpendeAn(array $zeile, Spender $spender, array $vorgaben): Spende
    {
        $betrag = $zeile['betrag'] ?? null;

        if ($betrag === null || (float) $betrag <= 0) {
            throw new RuntimeException('Kein gültiger Betrag.');
        }

        if (empty($zeile['spendendatum'])) {
            throw new RuntimeException('Kein gültiges Spendendatum.');
        }

        return Spende::create([
            'spender_id'          => $spender->id,
            'spendendatum'        => $zeile['spendendatum'],
            'betrag'              => $betrag,
            'betrag_in_worten'    => $this->wertOderNull($zeile['betrag_in_worten'] ?? null),
            'foerderungszweck_id' => $vorgaben['foerderungszweck_id'],
            'anlass'              => $this->wertOderNull($zeile['anlass'] ?? null),
            'ankreuzfeld'         => $vorgaben['ankreuzfeld'],
            'ausstellungsdatum'   => $vorgaben['ausstellungsdatum'],
            'alte_lfd_nr'         => $this->wertOderNull($zeile['alte_lfd_nr'] ?? null),
        ]);
    }

    private function wertOderNull(mixed $wert): ?string
    {
        $wert = trim((string) ($wert ?? ''));

        return $wert === '' ? null : $wert;
    }
}
