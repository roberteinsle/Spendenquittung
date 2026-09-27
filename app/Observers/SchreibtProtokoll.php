<?php

namespace App\Observers;

use App\Enums\ProtokollAktion;
use App\Services\ProtokollService;
use Illuminate\Database\Eloquent\Model;

/**
 * Hängt Anlegen, Ändern, Löschen und Wiederherstellen an den Prüfpfad.
 */
trait SchreibtProtokoll
{
    public function created(Model $model): void
    {
        app(ProtokollService::class)->schreibe(ProtokollAktion::Angelegt, $model);
    }

    public function updated(Model $model): void
    {
        $protokoll = app(ProtokollService::class);
        $aenderungen = $protokoll->aenderungen($model);

        // Nur technische Felder berührt – dafür gibt es eigene Einträge.
        if ($aenderungen === []) {
            return;
        }

        $protokoll->schreibe(
            aktion: ProtokollAktion::Bearbeitet,
            betrifft: $model,
            beschreibung: 'Geändert: '.implode(', ', array_keys($aenderungen)),
            aenderungen: $aenderungen,
        );
    }

    public function deleted(Model $model): void
    {
        app(ProtokollService::class)->schreibe(ProtokollAktion::Geloescht, $model);
    }

    public function restored(Model $model): void
    {
        app(ProtokollService::class)->schreibe(ProtokollAktion::Wiederhergestellt, $model);
    }
}
