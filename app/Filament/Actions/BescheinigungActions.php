<?php

namespace App\Filament\Actions;

use App\Models\Spende;
use App\Services\PdfGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Shared PDF actions, used by both the Bescheinigungen table and the edit page.
 */
class BescheinigungActions
{
    public static function pdfErzeugen(): Action
    {
        return Action::make('pdf_erzeugen')
            ->label(fn (Spende $record) => $record->pdf_pfad ? 'PDF neu erzeugen' : 'PDF erzeugen')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->requiresConfirmation(fn (Spende $record) => $record->pdf_pfad !== null)
            ->modalHeading('Bescheinigung neu erzeugen?')
            ->modalDescription('Das bestehende PDF wird dabei überschrieben.')
            ->modalSubmitActionLabel('Neu erzeugen')
            ->action(function (Spende $record) {
                try {
                    app(PdfGeneratorService::class)->generiere($record);

                    Notification::make()
                        ->title("PDF für Nr. {$record->bescheinigungsnummer} erzeugt")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('PDF konnte nicht erzeugt werden')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function pdfOeffnen(): Action
    {
        return Action::make('pdf_oeffnen')
            ->label('PDF öffnen')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->url(fn (Spende $record) => route('bescheinigung.pdf', $record))
            ->openUrlInNewTab()
            ->visible(fn (Spende $record) => $record->pdfVorhanden());
    }

    public static function pdfsErzeugenBulk(): BulkAction
    {
        return BulkAction::make('pdfs_erzeugen')
            ->label('PDFs erzeugen')
            ->icon('heroicon-o-document-plus')
            ->requiresConfirmation()
            ->modalHeading('PDFs für die ausgewählten Bescheinigungen erzeugen?')
            ->modalDescription('Bereits vorhandene PDFs werden überschrieben.')
            ->modalSubmitActionLabel('Erzeugen')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $generator = app(PdfGeneratorService::class);

                $erzeugt = 0;
                $fehler  = [];

                foreach ($records as $record) {
                    try {
                        $generator->generiere($record);
                        $erzeugt++;
                    } catch (Throwable $e) {
                        $fehler[] = "{$record->bescheinigungsnummer}: {$e->getMessage()}";
                    }
                }

                if ($fehler === []) {
                    Notification::make()
                        ->title("{$erzeugt} PDF(s) erzeugt")
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$erzeugt} PDF(s) erzeugt, " . count($fehler) . ' fehlgeschlagen')
                    ->body(implode("\n", array_slice($fehler, 0, 5)))
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }
}
