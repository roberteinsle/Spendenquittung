<?php

namespace App\Filament\Actions;

use App\Jobs\VersendeZuwendungsbestaetigung;
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

    public static function perEmailSenden(): Action
    {
        return Action::make('email_senden')
            ->label('Per E-Mail senden')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (Spende $record) => $record->pdfVorhanden())
            ->disabled(fn (Spende $record) => blank($record->spender?->email))
            ->tooltip(fn (Spende $record) => blank($record->spender?->email)
                ? 'Für diesen Spender ist keine E-Mail-Adresse hinterlegt.'
                : null
            )
            ->requiresConfirmation()
            ->modalHeading('Zuwendungsbestätigung per E-Mail senden?')
            ->modalDescription(fn (Spende $record) => "Nr. {$record->bescheinigungsnummer} geht als PDF-Anhang an {$record->spender?->email}.")
            ->modalSubmitActionLabel('Senden')
            ->action(function (Spende $record) {
                VersendeZuwendungsbestaetigung::dispatch($record, auth()->id());

                Notification::make()
                    ->title('E-Mail wird versendet')
                    ->body("Das Ergebnis erscheint im Versandprotokoll von Nr. {$record->bescheinigungsnummer}.")
                    ->success()
                    ->send();
            });
    }

    public static function perEmailSendenBulk(): BulkAction
    {
        return BulkAction::make('emails_senden')
            ->label('Per E-Mail senden')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalHeading('Ausgewählte Bescheinigungen per E-Mail senden?')
            ->modalDescription('Übersprungen werden Bescheinigungen ohne PDF und Spender ohne E-Mail-Adresse.')
            ->modalSubmitActionLabel('Senden')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $versendet   = 0;
                $ohnePdf     = 0;
                $ohneAdresse = 0;

                foreach ($records as $record) {
                    if (! $record->pdfVorhanden()) {
                        $ohnePdf++;

                        continue;
                    }

                    if (blank($record->spender?->email)) {
                        $ohneAdresse++;

                        continue;
                    }

                    VersendeZuwendungsbestaetigung::dispatch($record, auth()->id());
                    $versendet++;
                }

                $hinweise = [];
                if ($ohnePdf > 0) {
                    $hinweise[] = "{$ohnePdf} ohne PDF übersprungen";
                }
                if ($ohneAdresse > 0) {
                    $hinweise[] = "{$ohneAdresse} ohne E-Mail-Adresse übersprungen";
                }

                Notification::make()
                    ->title("{$versendet} E-Mail(s) in Versand gegeben")
                    ->body($hinweise === [] ? null : implode(', ', $hinweise))
                    ->status($versendet > 0 ? 'success' : 'warning')
                    ->send();
            });
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
