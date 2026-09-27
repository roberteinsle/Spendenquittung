<?php

namespace App\Filament\Actions;

use App\Enums\ExportFormat;
use App\Exports\Exportierbar;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export einer Tabelle nach Excel, Markdown oder XML.
 *
 * @template T of Exportierbar
 */
class ExportActions
{
    /**
     * Exportiert, was die Tabelle gerade zeigt – Filter und Suche also
     * eingeschlossen.
     *
     * @param  class-string<Exportierbar>  $definition
     */
    public static function tabelle(string $definition, string $titel): Action
    {
        return Action::make('export')
            ->label('Exportieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading("{$titel} exportieren")
            ->modalDescription('Exportiert alle Datensätze, die die aktuellen Filter und die Suche übrig lassen.')
            ->modalSubmitActionLabel('Herunterladen')
            ->schema(self::formular())
            ->action(function (array $data, HasTable $livewire) use ($definition): StreamedResponse {
                $datensaetze = $livewire->getFilteredSortedTableQuery()
                    ->with($definition::beziehungen())
                    ->get();

                return self::datei($definition, self::format($data['format']), $datensaetze);
            });
    }

    /**
     * @param  class-string<Exportierbar>  $definition
     */
    public static function auswahl(string $definition, string $titel): BulkAction
    {
        return BulkAction::make('export_auswahl')
            ->label('Auswahl exportieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading("Ausgewählte {$titel} exportieren")
            ->modalSubmitActionLabel('Herunterladen')
            ->schema(self::formular())
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, Collection $records) use ($definition): StreamedResponse {
                $records->loadMissing($definition::beziehungen());

                return self::datei($definition, self::format($data['format']), $records);
            });
    }

    /**
     * Ein Select mit ->options(EnumKlasse::class) liefert nach der Hydration ein
     * Enum-Objekt, beim Absenden aus dem Browser aber einen String. Beides muss
     * hier ankommen dürfen.
     */
    private static function format(mixed $wert): ExportFormat
    {
        return $wert instanceof ExportFormat
            ? $wert
            : ExportFormat::from((string) $wert);
    }

    /**
     * @return array<int, Select>
     */
    private static function formular(): array
    {
        return [
            Select::make('format')
                ->label('Format')
                ->options(ExportFormat::class)
                ->default(ExportFormat::Excel->value)
                ->required()
                ->native(false),
        ];
    }

    /**
     * @param  class-string<Exportierbar>  $definition
     * @param  \Illuminate\Support\Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $records
     */
    private static function datei(string $definition, ExportFormat $format, iterable $records): StreamedResponse
    {
        $zeilen = [];

        foreach ($records as $record) {
            $zeilen[] = $definition::zeile($record);
        }

        $inhalt = app(ExportService::class)->erzeuge(
            format: $format,
            spalten: $definition::spalten(),
            zeilen: $zeilen,
            wurzel: $definition::xmlWurzel(),
            eintrag: $definition::xmlEintrag(),
        );

        $dateiname = sprintf(
            '%s-%s.%s',
            $definition::dateiname(),
            now()->format('Y-m-d'),
            $format->dateiendung(),
        );

        Notification::make()
            ->title(count($zeilen).' Datensätze exportiert')
            ->success()
            ->send();

        return response()->streamDownload(
            fn () => print ($inhalt),
            $dateiname,
            ['Content-Type' => $format->mimeTyp()],
        );
    }
}
