<?php

namespace Tests\Feature;

use App\Filament\Pages\Einstellungen;
use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailKonfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SmtpEinstellungenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['ist_admin' => true, 'email' => 'chefin@example.test']);
    }

    private function smtpHinterlegen(): void
    {
        Setting::set('mail_host', 'smtp.beispiel.test');
        Setting::set('mail_port', '587');
        Setting::set('mail_benutzername', 'post@beispiel.test');
        Setting::set('mail_passwort', 'geheim123');
        Setting::set('mail_verschluesselung', 'tls');
        // Ohne Absender verweigert die Aktion den Versand.
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');
    }

    public function test_passwort_liegt_verschluesselt_in_der_datenbank(): void
    {
        Setting::set('mail_passwort', 'geheim123');

        $roh = DB::table('settings')->where('key', 'mail_passwort')->first();

        $this->assertSame('encrypted', $roh->type);
        $this->assertNotSame('geheim123', $roh->value);
        $this->assertStringNotContainsString('geheim123', $roh->value);

        // Gelesen wird trotzdem der Klartext.
        $this->assertSame('geheim123', Setting::get('mail_passwort'));
    }

    public function test_beschaedigtes_passwort_sperrt_die_seite_nicht(): void
    {
        Setting::set('mail_passwort', 'geheim123');
        DB::table('settings')->where('key', 'mail_passwort')->update(['value' => 'kein-gueltiger-chiffretext']);
        cache()->flush();

        $this->assertSame('', Setting::get('mail_passwort', ''));
    }

    public function test_einstellungen_ueberschreiben_die_mail_konfiguration(): void
    {
        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'aus-der-umgebung']);
        $this->smtpHinterlegen();

        $angewendet = app(MailKonfigurationService::class)->anwenden();

        $this->assertTrue($angewendet);
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.beispiel.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('geheim123', config('mail.mailers.smtp.password'));
        $this->assertNull(config('mail.mailers.smtp.scheme'));
    }

    public function test_ssl_setzt_das_passende_schema(): void
    {
        $this->smtpHinterlegen();
        Setting::set('mail_verschluesselung', 'ssl');

        app(MailKonfigurationService::class)->anwenden();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_ohne_server_bleibt_die_umgebung_unangetastet(): void
    {
        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'aus-der-umgebung']);

        $angewendet = app(MailKonfigurationService::class)->anwenden();

        $this->assertFalse($angewendet);
        $this->assertSame('log', config('mail.default'));
        $this->assertSame('aus-der-umgebung', config('mail.mailers.smtp.host'));
    }

    public function test_testmail_geht_an_die_angegebene_adresse(): void
    {
        Mail::fake();
        $this->smtpHinterlegen();

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->call('sendeTestmail', 'ziel@example.test');

        Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('ziel@example.test'));
    }

    public function test_testmail_nutzt_das_gespeicherte_passwort_wenn_das_feld_leer_bleibt(): void
    {
        Mail::fake();
        $this->smtpHinterlegen();

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->call('sendeTestmail', 'ziel@example.test');

        $this->assertSame('geheim123', config('mail.mailers.smtp.password'));
    }

    public function test_absender_kommt_aus_den_smtp_feldern(): void
    {
        Setting::set('stiftung_name', 'Musterstiftung');
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');
        Setting::set('mail_absender_name', 'Spendenbüro');
        Setting::set('mail_absender_email', 'post@smtp-anbieter.test');

        $absender = app(MailKonfigurationService::class)->absender();

        $this->assertSame('post@smtp-anbieter.test', $absender->address);
        $this->assertSame('Spendenbüro', $absender->name);
    }

    public function test_absender_faellt_auf_die_stiftungsdaten_zurueck(): void
    {
        Setting::set('stiftung_name', 'Musterstiftung');
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');
        Setting::set('mail_absender_name', '');
        Setting::set('mail_absender_email', '');

        $absender = app(MailKonfigurationService::class)->absender();

        $this->assertSame('kontakt@musterstiftung.test', $absender->address);
        $this->assertSame('Musterstiftung', $absender->name);
    }

    public function test_bescheinigungsmail_nutzt_denselben_absender(): void
    {
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');
        Setting::set('mail_absender_email', 'post@smtp-anbieter.test');
        Setting::set('mail_absender_name', 'Spendenbüro');

        $absender = app(MailKonfigurationService::class)->absender();

        $this->assertSame('post@smtp-anbieter.test', $absender->address);
        $this->assertSame('Spendenbüro', $absender->name);
    }

    public function test_anwenden_setzt_den_globalen_absender(): void
    {
        Setting::set('mail_absender_name', 'Spendenbüro');
        Setting::set('mail_absender_email', 'post@smtp-anbieter.test');

        app(MailKonfigurationService::class)->anwenden();

        $this->assertSame('post@smtp-anbieter.test', config('mail.from.address'));
        $this->assertSame('Spendenbüro', config('mail.from.name'));
    }

    public function test_testmail_nimmt_den_absender_aus_dem_formular(): void
    {
        Mail::fake();
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->set('data.mail_absender_email', 'neu@smtp-anbieter.test')
            ->set('data.mail_absender_name', 'Neuer Absender')
            ->call('sendeTestmail', 'ziel@example.test');

        Mail::assertSent(TestMail::class, function (TestMail $mail): bool {
            $absender = $mail->envelope()->from;

            return $absender->address === 'neu@smtp-anbieter.test'
                && $absender->name === 'Neuer Absender';
        });
    }

    public function test_ohne_server_warnt_die_aktion_statt_erfolg_zu_melden(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->call('sendeTestmail', 'ziel@example.test')
            ->assertNotified('Nichts versendet – nur ins Log geschrieben');
    }

    public function test_mit_server_meldet_die_aktion_erfolg_samt_server(): void
    {
        Mail::fake();
        $this->smtpHinterlegen();
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->set('data.mail_host', 'smtp.beispiel.test')
            ->set('data.mail_port', '587')
            ->call('sendeTestmail', 'ziel@example.test')
            ->assertNotified('Testmail verschickt');
    }

    public function test_ohne_absender_bricht_die_aktion_ab(): void
    {
        Mail::fake();
        Setting::set('stiftung_email', '');
        Setting::set('mail_absender_email', '');

        Livewire::actingAs($this->admin())
            ->test(Einstellungen::class)
            ->call('sendeTestmail', 'ziel@example.test')
            ->assertNotified('Kein Absender hinterlegt');

        Mail::assertNothingSent();
    }

    public function test_mitarbeiter_kommt_nicht_an_die_smtp_daten(): void
    {
        $this->smtpHinterlegen();

        $this->actingAs(User::factory()->create(['ist_admin' => false]))
            ->get(route('filament.admin.pages.einstellungen'))
            ->assertForbidden();
    }
}
