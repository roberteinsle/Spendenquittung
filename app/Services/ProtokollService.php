<?php

namespace App\Services;

use App\Enums\ProtokollAktion;
use App\Models\Protokolleintrag;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProtokollService
{
    /**
     * Felder, die die App selbst setzt. Sie erscheinen nicht als "bearbeitet",
     * weil für sie eigene Einträge geschrieben werden – sonst stünde neben
     * jedem "PDF erzeugt" ein nichtssagendes "Bearbeitet".
     */
    private const TECHNISCHE_FELDER = [
        'pdf_pfad',
        'status',
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
    ];

    /**
     * @param  array<string, mixed>|null  $aenderungen
     * @param  int|null  $benutzerId  Queue-Jobs laufen ohne auth().
     */
    public function schreibe(
        ProtokollAktion $aktion,
        ?Model $betrifft = null,
        ?string $beschreibung = null,
        ?array $aenderungen = null,
        ?int $benutzerId = null,
    ): Protokolleintrag {
        $benutzerId ??= auth()->id();
        $benutzer = $benutzerId ? User::find($benutzerId) : null;

        return Protokolleintrag::create([
            'benutzer_id' => $benutzerId,
            'benutzer_name' => $benutzer?->name,
            'aktion' => $aktion,
            'betrifft_type' => $betrifft ? $betrifft::class : null,
            'betrifft_id' => $betrifft?->getKey(),
            'bezeichnung' => $this->bezeichnung($betrifft),
            'beschreibung' => $beschreibung,
            'aenderungen' => $aenderungen,
        ]);
    }

    /**
     * Beschreibt den Datensatz so, dass der Eintrag auch dann noch verständlich
     * ist, wenn es ihn nicht mehr gibt.
     */
    public function bezeichnung(?Model $betrifft): string
    {
        return match (true) {
            $betrifft instanceof Spende => 'Bescheinigung '.$betrifft->bescheinigungsnummer
                .($betrifft->spender ? ' · '.$betrifft->spender->vollname : ''),
            $betrifft instanceof Spender => $betrifft->vollname.' ('.$betrifft->spendernummer.')',
            $betrifft === null => '—',
            default => class_basename($betrifft).' '.$betrifft->getKey(),
        };
    }

    /**
     * Die inhaltlich geänderten Felder mit altem und neuem Wert.
     *
     * @return array<string, array{alt: mixed, neu: mixed}>
     */
    public function aenderungen(Model $model): array
    {
        $aenderungen = [];

        foreach ($model->getChanges() as $feld => $neu) {
            if (in_array($feld, self::TECHNISCHE_FELDER, true)) {
                continue;
            }

            $aenderungen[$feld] = [
                'alt' => $this->lesbar($model->getOriginal($feld)),
                'neu' => $this->lesbar($neu),
            ];
        }

        return $aenderungen;
    }

    private function lesbar(mixed $wert): mixed
    {
        return match (true) {
            $wert instanceof \BackedEnum => $wert->value,
            $wert instanceof \DateTimeInterface => $wert->format('d.m.Y'),
            is_bool($wert) => $wert ? 'ja' : 'nein',
            default => $wert,
        };
    }
}
