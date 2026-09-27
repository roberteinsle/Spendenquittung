<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Übernimmt den in den Einstellungen hinterlegten SMTP-Zugang in die
 * Laufzeit-Konfiguration.
 *
 * Bewusst kein Service-Provider-Hook: der würde bei jedem Boot die Datenbank
 * anfassen, auch beim Migrieren eines frischen Containers. Stattdessen ruft
 * jeder Versandweg diese Methode kurz vor dem Senden auf.
 */
class MailKonfigurationService
{
    /**
     * @param  array<string, mixed>|null  $daten  Werte aus dem Formular; null lädt
     *                                            die gespeicherten Einstellungen.
     * @return bool true, wenn ein SMTP-Zugang aus den Einstellungen greift.
     */
    public function anwenden(?array $daten = null): bool
    {
        $daten ??= $this->ausEinstellungen();

        // Der Absender gilt auch dann, wenn der Server aus der Umgebung kommt.
        if ($absender = $this->absender($daten)) {
            config([
                'mail.from.address' => $absender->address,
                'mail.from.name' => $absender->name ?: null,
            ]);
        }

        if (blank($daten['host'] ?? null)) {
            // Nichts hinterlegt: es bleibt bei dem, was die Umgebung vorgibt.
            return false;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $daten['host'],
            'mail.mailers.smtp.port' => (int) ($daten['port'] ?: 587),
            'mail.mailers.smtp.username' => $daten['benutzername'] ?: null,
            'mail.mailers.smtp.password' => $daten['passwort'] ?: null,
            'mail.mailers.smtp.scheme' => $daten['verschluesselung'] === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.timeout' => 15,
        ]);

        // Ohne das liefe ein bereits aufgebauter Mailer mit der alten
        // Konfiguration weiter – im Queue-Worker über Stunden.
        Mail::purge('smtp');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function ausEinstellungen(): array
    {
        return [
            'host' => Setting::get('mail_host', ''),
            'port' => Setting::get('mail_port', ''),
            'benutzername' => Setting::get('mail_benutzername', ''),
            'passwort' => Setting::get('mail_passwort', ''),
            'verschluesselung' => Setting::get('mail_verschluesselung', 'tls'),
            'absender_email' => Setting::get('mail_absender_email', ''),
            'absender_name' => Setting::get('mail_absender_name', ''),
        ];
    }

    /**
     * Absender für alle ausgehenden Nachrichten. Ist unter SMTP nichts
     * hinterlegt, gilt die Adresse aus den Stiftungsdaten – viele Provider
     * verlangen allerdings, dass sie zum SMTP-Konto passt.
     *
     * @param  array<string, mixed>|null  $daten
     */
    public function absender(?array $daten = null): ?Address
    {
        $daten ??= $this->ausEinstellungen();

        $email = trim((string) ($daten['absender_email'] ?? '')) ?: (string) Setting::get('stiftung_email', '');
        $name = trim((string) ($daten['absender_name'] ?? '')) ?: (string) Setting::get('stiftung_name', '');

        return filled($email) ? new Address($email, $name ?: null) : null;
    }

    public function istKonfiguriert(): bool
    {
        return filled(Setting::get('mail_host', ''));
    }
}
