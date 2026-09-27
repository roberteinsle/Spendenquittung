<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\Anrede;
use App\Enums\SpendeStatus;
use App\Enums\VersandErgebnis;
use App\Enums\VersandKanal;
use App\Jobs\VersendeZuwendungsbestaetigung;
use App\Mail\ZuwendungsbestaetigungMail;
use App\Models\Foerderungszweck;
use App\Models\Setting;
use App\Models\Spende;
use App\Models\Spender;
use App\Filament\Resources\Spendes\Pages\ListSpendes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVersandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
        Storage::fake(config('spendenquittung.pdf_disk'));

        Setting::set('stiftung_name', 'Musterstiftung');
        Setting::set('stiftung_email', 'kontakt@musterstiftung.test');
        Setting::set('unterzeichner_name', 'A. Vorstand');
        Setting::set('unterzeichner_titel', 'Vorstand');
        Setting::set('mail_betreff', 'Ihre Zuwendungsbestätigung Nr. :nummer');
        Setting::set('mail_text', 'vielen Dank für Ihre Spende vom :datum über :betrag.');
        Setting::set('mail_text_du', 'vielen Dank für Deine Spende vom :datum über :betrag.');
    }

    private function spendeAnlegen(array $spenderAttribute = [], bool $mitPdf = true): Spende
    {
        $spender = Spender::create(array_merge([
            'spendernummer' => '80001',
            'anrede'        => Anrede::Herrn,
            'vorname'       => 'Max',
            'nachname'      => 'Mustermann',
            'email'         => 'max@example.test',
            'aktiv'         => true,
        ], $spenderAttribute));

        $zweck = Foerderungszweck::create([
            'name'       => 'Bildung',
            'text'       => 'Förderung der Erziehung, Volks- und Berufsbildung',
            'aktiv'      => true,
            'sortierung' => 1,
        ]);

        $spende = Spende::create([
            'spender_id'          => $spender->id,
            'spendendatum'        => '2026-03-01',
            'betrag'              => 70.70,
            'foerderungszweck_id' => $zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar,
            'status'              => SpendeStatus::Erstellt,
        ]);

        if ($mitPdf) {
            $pfad = config('spendenquittung.pdf_storage_path') . '/' . $spende->bescheinigungsnummer . '.pdf';
            Storage::disk(config('spendenquittung.pdf_disk'))->put($pfad, '%PDF-1.4 fake');
            $spende->update(['pdf_pfad' => $pfad]);
        }

        return $spende->refresh();
    }

    public function test_mail_wird_mit_pdf_anhang_versendet(): void
    {
        Mail::fake();
        $spende = $this->spendeAnlegen();
        $user   = User::factory()->create();

        (new VersendeZuwendungsbestaetigung($spende, $user->id))
            ->handle(app(\App\Services\VersandprotokollService::class));

        Mail::assertSent(ZuwendungsbestaetigungMail::class, function (ZuwendungsbestaetigungMail $mail) use ($spende) {
            return $mail->hasTo('max@example.test')
                && $mail->spende->is($spende);
        });
    }

    public function test_erfolgreicher_versand_wird_protokolliert_und_setzt_status(): void
    {
        Mail::fake();
        $spende = $this->spendeAnlegen();
        $user   = User::factory()->create();

        (new VersendeZuwendungsbestaetigung($spende, $user->id))
            ->handle(app(\App\Services\VersandprotokollService::class));

        $protokoll = $spende->versandprotokolle()->sole();
        $this->assertSame(VersandKanal::Email, $protokoll->kanal);
        $this->assertSame(VersandErgebnis::Erfolg, $protokoll->ergebnis);
        $this->assertSame('max@example.test', $protokoll->empfaenger);
        $this->assertSame($user->id, $protokoll->ausgefuehrt_von);

        $this->assertSame(SpendeStatus::Versendet, $spende->refresh()->status);
    }

    public function test_versand_ohne_email_adresse_schlaegt_fehl(): void
    {
        Mail::fake();
        $spende = $this->spendeAnlegen(['email' => null]);

        $this->expectException(\RuntimeException::class);

        try {
            (new VersendeZuwendungsbestaetigung($spende))
                ->handle(app(\App\Services\VersandprotokollService::class));
        } finally {
            Mail::assertNothingSent();
            $this->assertSame(SpendeStatus::Erstellt, $spende->refresh()->status);
        }
    }

    public function test_versand_ohne_pdf_schlaegt_fehl(): void
    {
        Mail::fake();
        $spende = $this->spendeAnlegen(mitPdf: false);

        $this->expectException(\RuntimeException::class);

        try {
            (new VersendeZuwendungsbestaetigung($spende))
                ->handle(app(\App\Services\VersandprotokollService::class));
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_endgueltiger_fehlschlag_wird_protokolliert(): void
    {
        $spende = $this->spendeAnlegen();
        $user   = User::factory()->create();

        (new VersendeZuwendungsbestaetigung($spende, $user->id))
            ->failed(new \RuntimeException('SMTP nicht erreichbar'));

        $protokoll = $spende->versandprotokolle()->sole();
        $this->assertSame(VersandErgebnis::Fehler, $protokoll->ergebnis);
        $this->assertSame('SMTP nicht erreichbar', $protokoll->nachricht);
        $this->assertSame($user->id, $protokoll->ausgefuehrt_von);

        // A failure must not move the receipt forward.
        $this->assertSame(SpendeStatus::Erstellt, $spende->refresh()->status);
    }

    public function test_mail_nutzt_anrede_betreff_und_platzhalter(): void
    {
        $spende = $this->spendeAnlegen();

        $mail     = new ZuwendungsbestaetigungMail($spende);
        $envelope = $mail->envelope();
        $gerendert = $mail->render();

        $this->assertSame("Ihre Zuwendungsbestätigung Nr. {$spende->bescheinigungsnummer}", $envelope->subject);
        $this->assertSame('kontakt@musterstiftung.test', $envelope->from->address);
        $this->assertStringContainsString('Sehr geehrter Herr Mustermann', $gerendert);
        $this->assertStringContainsString('01.03.2026', $gerendert);
        $this->assertStringContainsString('70,70', $gerendert);
        $this->assertStringContainsString('A. Vorstand', $gerendert);
    }

    public function test_mail_haengt_das_pdf_an(): void
    {
        $spende = $this->spendeAnlegen();

        (new ZuwendungsbestaetigungMail($spende))
            ->assertHasAttachedData(
                '%PDF-1.4 fake',
                $spende->pdf_dateiname,
                ['mime' => 'application/pdf'],
            );
    }

    public function test_tabellenaktion_gibt_den_versand_in_die_queue(): void
    {
        Bus::fake();
        $spende = $this->spendeAnlegen();

        Livewire::actingAs(User::factory()->create())
            ->test(ListSpendes::class)
            ->callTableAction('email_senden', $spende);

        Bus::assertDispatched(
            VersendeZuwendungsbestaetigung::class,
            fn (VersendeZuwendungsbestaetigung $job) => $job->spende->is($spende),
        );
    }

    public function test_tabellenaktion_fehlt_ohne_pdf(): void
    {
        $spende = $this->spendeAnlegen(mitPdf: false);

        Livewire::actingAs(User::factory()->create())
            ->test(ListSpendes::class)
            ->assertTableActionHidden('email_senden', $spende);
    }

    public function test_mail_traegt_die_organisation_und_keinen_englischen_baustein(): void
    {
        Setting::set('stiftung_web', 'www.musterstiftung.test');
        $spende = $this->spendeAnlegen();

        $gerendert = (new ZuwendungsbestaetigungMail($spende))->render();

        $this->assertStringContainsString('Musterstiftung', $gerendert);
        $this->assertStringContainsString('https://www.musterstiftung.test', $gerendert);
        $this->assertStringNotContainsString('All rights reserved', $gerendert);
    }

    public function test_geduzte_spender_erhalten_den_du_text(): void
    {
        $spende = $this->spendeAnlegen(['duzen' => true]);

        $gerendert = (new ZuwendungsbestaetigungMail($spende))->render();

        $this->assertStringContainsString('Lieber Max', $gerendert);
        $this->assertStringContainsString('Deine Spende', $gerendert);
        $this->assertStringNotContainsString('Ihre Spende', $gerendert);
    }
}
