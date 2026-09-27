<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Spende;
use App\Services\MailKonfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ZuwendungsbestaetigungMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Spende $spende,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: app(MailKonfigurationService::class)->absender(),
            subject: $this->platzhalterErsetzen(
                Setting::get('mail_betreff') ?: 'Ihre Zuwendungsbestätigung Nr. :nummer',
            ),
        );
    }

    public function content(): Content
    {
        $schluessel = $this->spende->spender?->duzen ? 'mail_text_du' : 'mail_text';

        return new Content(
            markdown: 'mail.zuwendungsbestaetigung',
            with: [
                'briefanrede' => $this->spende->spender?->briefanrede ?? 'Guten Tag',
                'text' => $this->platzhalterErsetzen((string) Setting::get($schluessel, '')),
                'settings' => [
                    'unterzeichner_name' => Setting::get('unterzeichner_name', ''),
                    'unterzeichner_titel' => Setting::get('unterzeichner_titel', ''),
                    'stiftung_name' => Setting::get('stiftung_name', ''),
                    'stiftung_web' => Setting::get('stiftung_web', ''),
                    'stiftung_url' => $this->stiftungUrl(),
                ],
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(
                config('spendenquittung.pdf_disk'),
                $this->spende->pdf_pfad,
            )
                ->as($this->spende->pdf_dateiname)
                ->withMime('application/pdf'),
        ];
    }

    /**
     * The website is stored for display ("www.example.de"), so it needs a scheme
     * before it can be used as a link target.
     */
    private function stiftungUrl(): string
    {
        $web = trim((string) Setting::get('stiftung_web', ''));

        if ($web === '') {
            return (string) config('app.url');
        }

        return str_starts_with($web, 'http://') || str_starts_with($web, 'https://')
            ? $web
            : "https://{$web}";
    }

    /**
     * Fill the placeholders an administrator may use in subject and body.
     */
    private function platzhalterErsetzen(string $vorlage): string
    {
        return str_replace(
            [':nummer', ':betrag', ':datum', ':jahr', ':zweck'],
            [
                (string) $this->spende->bescheinigungsnummer,
                $this->spende->betrag_formatiert,
                $this->spende->spendendatum?->format('d.m.Y') ?? '',
                (string) ($this->spende->spendendatum?->year ?? ''),
                (string) ($this->spende->foerderungszweck?->name ?? ''),
            ],
            $vorlage,
        );
    }
}
