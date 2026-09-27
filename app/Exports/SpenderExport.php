<?php

namespace App\Exports;

use App\Models\Spender;

class SpenderExport implements Exportierbar
{
    public static function dateiname(): string
    {
        return 'spender';
    }

    public static function xmlWurzel(): string
    {
        return 'spender_liste';
    }

    public static function xmlEintrag(): string
    {
        return 'spender';
    }

    public static function spalten(): array
    {
        return [
            'spendernummer' => 'Spendernummer',
            'anrede' => 'Anrede',
            'firma' => 'Firma',
            'vorname' => 'Vorname',
            'nachname' => 'Nachname',
            'strasse' => 'Straße',
            'plz' => 'PLZ',
            'ort' => 'Ort',
            'email' => 'E-Mail',
            'duzen' => 'Duzen',
            'aktiv' => 'Aktiv',
            'bemerkung' => 'Bemerkung',
        ];
    }

    public static function beziehungen(): array
    {
        return [];
    }

    /**
     * @param  Spender  $record
     */
    public static function zeile($record): array
    {
        return [
            'spendernummer' => (string) $record->spendernummer,
            'anrede' => $record->anrede?->value ?? '',
            'firma' => (string) ($record->firma ?? ''),
            'vorname' => (string) ($record->vorname ?? ''),
            'nachname' => (string) ($record->nachname ?? ''),
            'strasse' => (string) ($record->strasse ?? ''),
            'plz' => (string) ($record->plz ?? ''),
            'ort' => (string) ($record->ort ?? ''),
            'email' => (string) ($record->email ?? ''),
            'duzen' => (bool) $record->duzen,
            'aktiv' => (bool) $record->aktiv,
            'bemerkung' => (string) ($record->bemerkung ?? ''),
        ];
    }
}
