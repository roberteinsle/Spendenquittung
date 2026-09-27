<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Model;

/**
 * Beschreibt, welche Felder einer Tabelle exportiert werden. Die Export-Aktion
 * arbeitet nur gegen dieses Interface und kennt die Modelle nicht.
 */
interface Exportierbar
{
    /** Basisname der Datei, ohne Endung. */
    public static function dateiname(): string;

    public static function xmlWurzel(): string;

    public static function xmlEintrag(): string;

    /** @return array<string, string> Schlüssel => Überschrift */
    public static function spalten(): array;

    /**
     * Beziehungen, die eine Zeile braucht – sonst eine Abfrage je Datensatz.
     *
     * @return array<int, string>
     */
    public static function beziehungen(): array;

    /** @return array<string, mixed> */
    public static function zeile(Model $record): array;
}
