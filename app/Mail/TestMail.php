<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        $absender = Setting::get('stiftung_email');
        $name = Setting::get('stiftung_name');

        return new Envelope(
            from: $absender ? new Address($absender, $name ?: null) : null,
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
