<?php

namespace App\Exports;

use App\Models\Protokolleintrag;

class ProtokollExport implements Exportierbar
{
    public static function dateiname(): string
    {
        return 'protokoll';
    }

    public static function xmlWurzel(): string
    {
        return 'protokoll';
    }

    public static function xmlEintrag(): string
    {
        return 'eintrag';
    }

    public static function spalten(): array
    {
        return [
            'zeitpunkt' => 'Zeitpunkt',
            'benutzer' => 'Benutzer',
            'aktion' => 'Aktion',
            'art' => 'Art',
            'bezeichnung' => 'Betrifft',
            'beschreibung' => 'Details',
        ];
    }

    public static function beziehungen(): array
    {
        return [];
    }

    /**
     * @param  Protokolleintrag  $record
     */
    public static function zeile($record): array
    {
        return [
            'zeitpunkt' => $record->created_at?->format('d.m.Y H:i') ?? '',
            'benutzer' => (string) ($record->benutzer_name ?? 'System'),
            'aktion' => $record->aktion?->getLabel() ?? '',
            'art' => $record->art,
            'bezeichnung' => (string) $record->bezeichnung,
            'beschreibung' => (string) ($record->beschreibung ?? ''),
        ];
    }
}
