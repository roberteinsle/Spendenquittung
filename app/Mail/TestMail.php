<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\MailKonfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Address|null  $absender  Erlaubt den Test mit noch ungespeicherten
     *                                  Formularwerten.
     */
    public function __construct(
        private ?Address $absender = null,
    ) {}

    public function envelope(): Envelope
    {
        $name = Setting::get('stiftung_name');

        return new Envelope(
            from: $this->absender ?? app(MailKonfigurationService::class)->absender(),
            subject: 'Testmail aus '.($name ?: config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.test',
            with: [
                'stiftung' => Setting::get('stiftung_name', ''),
                'zeitpunkt' => now()->format('d.m.Y H:i'),
            ],
        );
    }
}
