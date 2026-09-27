<?php

namespace App\Exports;

use App\Models\Spende;

class SpendenExport implements Exportierbar
{
    public static function dateiname(): string
    {
        return 'bescheinigungen';
    }

    public static function xmlWurzel(): string
    {
        return 'bescheinigungen';
    }

    public static function xmlEintrag(): string
    {
        return 'bescheinigung';
    }

    public static function spalten(): array
    {
        return [
            'bescheinigungsnummer' => 'Bescheinigungsnummer',
            'spendendatum' => 'Spendendatum',
            'betrag' => 'Betrag',
            'betrag_in_worten' => 'Betrag in Worten',
            'spendernummer' => 'Spendernummer',
            'spender' => 'Spender',
            'strasse' => 'Straße',
            'plz' => 'PLZ',
            'ort' => 'Ort',
            'foerderungszweck' => 'Förderungszweck',
            'anlass' => 'Anlass',
            'ankreuzfeld' => 'Verwendung',
            'ausstellungsdatum' => 'Ausstellungsdatum',
            'status' => 'Status',
        ];
    }

    public static function beziehungen(): array
    {
        return ['spender', 'foerderungszweck'];
    }

    /**
     * @param  Spende  $record
     */
    public static function zeile($record): array
    {
        return [
            'bescheinigungsnummer' => (string) $record->bescheinigungsnummer,
            'spendendatum' => $record->spendendatum?->format('d.m.Y') ?? '',
            'betrag' => (float) $record->betrag,
            'betrag_in_worten' => (string) $record->betrag_in_worten,
            'spendernummer' => (string) ($record->spender?->spendernummer ?? ''),
            'spender' => (string) ($record->spender?->vollname ?? ''),
            'strasse' => (string) ($record->spender?->strasse ?? ''),
            'plz' => (string) ($record->spender?->plz ?? ''),
            'ort' => (string) ($record->spender?->ort ?? ''),
            'foerderungszweck' => (string) ($record->foerderungszweck?->name ?? ''),
            'anlass' => (string) ($record->anlass ?? ''),
            'ankreuzfeld' => $record->ankreuzfeld?->getLabelShort() ?? '',
            'ausstellungsdatum' => $record->ausstellungsdatum?->format('d.m.Y') ?? '',
            'status' => $record->status?->getLabel() ?? '',
        ];
    }
}
