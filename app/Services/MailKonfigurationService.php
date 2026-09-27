<?php

namespace App\Services;

use App\Models\Setting;
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
        ];
    }

    public function istKonfiguriert(): bool
    {
        return filled(Setting::get('mail_host', ''));
    }
}
