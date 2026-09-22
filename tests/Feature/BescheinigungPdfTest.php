<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\Anrede;
use App\Enums\SpendeStatus;
use App\Enums\VersandKanal;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BescheinigungPdfTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_BYTES = '%PDF-1.4 fake';

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);

        Storage::fake(config('spendenquittung.pdf_disk'));
        Storage::fake('public');
    }

    private function spendeAnlegen(array $attribute = []): Spende
    {
        $spender = Spender::create([
            'spendernummer' => '80001',
            'anrede'        => Anrede::Herrn,
            'vorname'       => 'Max',
            'nachname'      => 'Mustermann',
            'strasse'       => 'Musterweg 1',
            'plz'           => '20095',
            'ort'           => 'Hamburg',
            'email'         => 'max@example.com',
            'aktiv'         => true,
        ]);

        $zweck = Foerderungszweck::create([
            'name'       => 'Bildung',
            'text'       => 'Förderung der Erziehung, Volks- und Berufsbildung',
            'aktiv'      => true,
            'sortierung' => 1,
        ]);

        return Spende::create(array_merge([
            'spender_id'          => $spender->id,
            'spendendatum'        => '2026-03-01',
            'betrag'              => 70.70,
            'foerderungszweck_id' => $zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar,
        ], $attribute));
    }

    private function gotenbergFaken(): void
    {
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response(self::PDF_BYTES, 200),
        ]);
    }

    public function test_pdf_wird_erzeugt_und_gespeichert(): void
    {
        $this->gotenbergFaken();
        $spende = $this->spendeAnlegen();

        $pfad = app(PdfGeneratorService::class)->generiere($spende);

        Storage::disk(config('spendenquittung.pdf_disk'))->assertExists($pfad);
        $this->assertSame(self::PDF_BYTES, Storage::disk(config('spendenquittung.pdf_disk'))->get($pfad));

        $spende->refresh();
        $this->assertSame($pfad, $spende->pdf_pfad);
        $this->assertSame(SpendeStatus::Erstellt, $spende->status);
        $this->assertTrue($spende->pdfVorhanden());
    }

    public function test_pdf_landet_nicht_auf_der_oeffentlichen_disk(): void
    {
        $this->gotenbergFaken();
        $spende = $this->spendeAnlegen();

        $pfad = app(PdfGeneratorService::class)->generiere($spende);

        Storage::disk('public')->assertMissing($pfad);
    }

    public function test_neuerzeugung_setzt_status_nicht_zurueck(): void
    {
        $this->gotenbergFaken();
        $spende = $this->spendeAnlegen(['status' => SpendeStatus::Versendet]);

        app(PdfGeneratorService::class)->generiere($spende);

        $this->assertSame(SpendeStatus::Versendet, $spende->refresh()->status);
    }

    public function test_download_ist_ohne_login_gesperrt(): void
    {
        $this->gotenbergFaken();
        $spende = $this->spendeAnlegen();
        app(PdfGeneratorService::class)->generiere($spende);

        $this->get(route('bescheinigung.pdf', $spende))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_download_liefert_pdf_und_protokolliert_druck(): void
    {
        $this->gotenbergFaken();
        $user   = User::factory()->create();
        $spende = $this->spendeAnlegen();
        app(PdfGeneratorService::class)->generiere($spende);

        $response = $this->actingAs($user)->get(route('bescheinigung.pdf', $spende));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame(self::PDF_BYTES, $response->streamedContent());

        $spende->refresh();
        $this->assertSame(SpendeStatus::Gedruckt, $spende->status);

        $protokoll = $spende->versandprotokolle()->sole();
        $this->assertSame(VersandKanal::Druck, $protokoll->kanal);
        $this->assertSame($user->id, $protokoll->ausgefuehrt_von);
    }

    public function test_download_ohne_erzeugtes_pdf_ist_404(): void
    {
        $user   = User::factory()->create();
        $spende = $this->spendeAnlegen();

        $this->actingAs($user)
            ->get(route('bescheinigung.pdf', $spende))
            ->assertNotFound();
    }
}
