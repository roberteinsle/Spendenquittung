<?php

namespace App\Jobs;

use App\Enums\VersandErgebnis;
use App\Enums\VersandKanal;
use App\Mail\ZuwendungsbestaetigungMail;
use App\Models\Spende;
use App\Services\MailKonfigurationService;
use App\Services\VersandprotokollService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class VersendeZuwendungsbestaetigung implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Wait a minute, then five, before giving up — SMTP hiccups are usually
     * over by then.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public Spende $spende,
        public ?int $benutzerId = null,
    ) {}

    public function handle(
        VersandprotokollService $versandprotokoll,
        MailKonfigurationService $mailKonfiguration,
    ): void {
        $empfaenger = $this->empfaenger();

        if (! $this->spende->pdfVorhanden()) {
            throw new RuntimeException('Für diese Bescheinigung ist kein PDF vorhanden.');
        }

        // Der Worker lebt lange und hat die Mail-Konfiguration vom Start; der
        // in den Einstellungen hinterlegte Zugang muss deshalb je Job greifen.
        $mailKonfiguration->anwenden();

        Mail::to($empfaenger)->send(new ZuwendungsbestaetigungMail($this->spende));

        $versandprotokoll->protokolliere(
            spende: $this->spende,
            kanal: VersandKanal::Email,
            ergebnis: VersandErgebnis::Erfolg,
            empfaenger: $empfaenger,
            nachricht: 'Zuwendungsbestätigung per E-Mail versendet',
            benutzerId: $this->benutzerId,
        );
    }

    /**
     * Runs once, after the last attempt failed. Recording it here rather than in
     * handle() keeps retries from piling up entries in the protocol.
     */
    public function failed(?Throwable $e): void
    {
        app(VersandprotokollService::class)->protokolliere(
            spende: $this->spende,
            kanal: VersandKanal::Email,
            ergebnis: VersandErgebnis::Fehler,
            empfaenger: $this->spende->spender?->email,
            nachricht: $e?->getMessage() ?? 'Unbekannter Fehler beim Versand',
            benutzerId: $this->benutzerId,
        );
    }

    private function empfaenger(): string
    {
        $email = $this->spende->spender?->email;

        if (blank($email)) {
            throw new RuntimeException('Für diesen Spender ist keine E-Mail-Adresse hinterlegt.');
        }

        return $email;
    }
}
