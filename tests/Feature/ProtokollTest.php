<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\ProtokollAktion;
use App\Enums\VersandKanal;
use App\Filament\Resources\Protokolle\Pages\ListProtokolle;
use App\Filament\Resources\Protokolle\ProtokollResource;
use App\Models\Foerderungszweck;
use App\Models\Protokolleintrag;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use App\Services\PdfGeneratorService;
use App\Services\VersandprotokollService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProtokollTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    private Foerderungszweck $zweck;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
        Storage::fake(config('spendenquittung.pdf_disk'));

        $this->benutzer = User::factory()->create(['name' => 'Andrea Beispiel', 'ist_admin' => true]);
        $this->actingAs($this->benutzer);

        $this->zweck = Foerderungszweck::create([
            'name' => 'Bildung',
            'text' => 'Förderung der Bildung',
            'aktiv' => true,
            'sortierung' => 1,
        ]);
    }

    private function spender(string $nachname = 'Mustermann', string $nummer = '80001'): Spender
    {
        return Spender::create([
            'spendernummer' => $nummer,
            'vorname' => 'Max',
            'nachname' => $nachname,
            'aktiv' => true,
        ]);
    }

    private function spende(Spender $spender): Spende
    {
        return Spende::create([
            'spender_id' => $spender->id,
            'spendendatum' => '2026-03-01',
            'betrag' => 70.70,
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld' => AnkreuzfeldTyp::Unmittelbar,
        ])->refresh();
    }

    private function eintraege(ProtokollAktion $aktion): Collection
    {
        return Protokolleintrag::where('aktion', $aktion)->get();
    }

    public function test_anlegen_wird_mit_benutzer_protokolliert(): void
    {
        $spender = $this->spender();

        $eintrag = $this->eintraege(ProtokollAktion::Angelegt)
            ->firstWhere('betrifft_id', $spender->id);

        $this->assertNotNull($eintrag);
        $this->assertSame('Andrea Beispiel', $eintrag->benutzer_name);
        $this->assertSame($this->benutzer->id, $eintrag->benutzer_id);
        $this->assertSame('Max Mustermann (80001)', $eintrag->bezeichnung);
        $this->assertSame('Spender', $eintrag->art);
    }

    public function test_bearbeiten_haelt_alten_und_neuen_wert_fest(): void
    {
        $spender = $this->spender();
        $spender->update(['ort' => 'Hamburg', 'nachname' => 'Musterfrau']);

        $eintrag = $this->eintraege(ProtokollAktion::Bearbeitet)->first();

        $this->assertNotNull($eintrag);
        $this->assertSame(['alt' => null, 'neu' => 'Hamburg'], $eintrag->aenderungen['ort']);
        $this->assertSame(['alt' => 'Mustermann', 'neu' => 'Musterfrau'], $eintrag->aenderungen['nachname']);
        $this->assertStringContainsString('ort', $eintrag->beschreibung);
    }

    public function test_loeschen_und_wiederherstellen_werden_protokolliert(): void
    {
        $spender = $this->spender();
        $spender->delete();
        $spender->restore();

        $this->assertCount(1, $this->eintraege(ProtokollAktion::Geloescht));
        $this->assertCount(1, $this->eintraege(ProtokollAktion::Wiederhergestellt));
    }

    public function test_pdf_erzeugen_erscheint_im_protokoll(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4', 200)]);
        $spende = $this->spende($this->spender());

        app(PdfGeneratorService::class)->generiere($spende);

        $eintrag = $this->eintraege(ProtokollAktion::PdfErzeugt)->first();
        $this->assertNotNull($eintrag);
        $this->assertStringContainsString('Bescheinigung', $eintrag->bezeichnung);
        $this->assertSame('Andrea Beispiel', $eintrag->benutzer_name);
    }

    public function test_pdf_erzeugen_erzeugt_keinen_bearbeitet_eintrag(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4', 200)]);
        $spende = $this->spende($this->spender());

        app(PdfGeneratorService::class)->generiere($spende);

        // pdf_pfad und status sind technische Felder – sonst stünde neben
        // jedem "PDF erzeugt" ein nichtssagendes "Bearbeitet".
        $this->assertCount(0, $this->eintraege(ProtokollAktion::Bearbeitet));
    }

    public function test_versand_erscheint_im_protokoll(): void
    {
        $spende = $this->spende($this->spender());

        app(VersandprotokollService::class)->protokolliere(
            spende: $spende,
            kanal: VersandKanal::Email,
            empfaenger: 'max@example.test',
        );

        $eintrag = $this->eintraege(ProtokollAktion::EmailVersendet)->first();
        $this->assertNotNull($eintrag);
        $this->assertSame('An max@example.test', $eintrag->beschreibung);
    }

    public function test_druck_erscheint_im_protokoll(): void
    {
        $spende = $this->spende($this->spender());

        app(VersandprotokollService::class)->protokolliere(
            spende: $spende,
            kanal: VersandKanal::Druck,
        );

        $this->assertCount(1, $this->eintraege(ProtokollAktion::PdfGeoeffnet));
    }

    public function test_eintrag_ueberlebt_das_loeschen_des_benutzers(): void
    {
        $this->spender();
        $this->benutzer->delete();

        $eintrag = $this->eintraege(ProtokollAktion::Angelegt)->first();

        $this->assertNull($eintrag->fresh()->benutzer_id);
        // Der Name bleibt stehen, sonst wäre der Prüfpfad wertlos.
        $this->assertSame('Andrea Beispiel', $eintrag->fresh()->benutzer_name);
    }

    public function test_liste_laesst_sich_nach_aktion_filtern(): void
    {
        $spender = $this->spender();
        $spender->update(['ort' => 'Hamburg']);

        Livewire::test(ListProtokolle::class)
            ->loadTable()
            ->assertCanSeeTableRecords(Protokolleintrag::all())
            ->filterTable('aktion', [ProtokollAktion::Bearbeitet->value])
            ->assertCanSeeTableRecords($this->eintraege(ProtokollAktion::Bearbeitet))
            ->assertCanNotSeeTableRecords($this->eintraege(ProtokollAktion::Angelegt));
    }

    public function test_liste_laesst_sich_durchsuchen(): void
    {
        $this->spender('Suchbar', '80001');
        $this->spender('Unauffaellig', '80002');

        Livewire::test(ListProtokolle::class)
            ->loadTable()
            ->searchTable('Suchbar')
            ->assertCanSeeTableRecords(Protokolleintrag::where('bezeichnung', 'like', '%Suchbar%')->get())
            ->assertCanNotSeeTableRecords(Protokolleintrag::where('bezeichnung', 'like', '%Unauffaellig%')->get());
    }

    public function test_detaildialog_zeigt_die_aenderungen(): void
    {
        $spender = $this->spender();
        $spender->update(['ort' => 'Hamburg']);

        $eintrag = $this->eintraege(ProtokollAktion::Bearbeitet)->first();

        // mount statt call: der Dialog zeigt nur an, es gibt nichts auszuführen.
        // Geprüft wird, dass das Infolist-Schema trägt – den Modalinhalt selbst
        // rendert Filament erst im Browser, er steht nicht im Livewire-HTML.
        Livewire::test(ListProtokolle::class)
            ->loadTable()
            ->mountTableAction('details', $eintrag)
            ->assertHasNoActionErrors();

        // Der Inhalt, den der Dialog zeigt:
        $this->assertSame(['alt' => null, 'neu' => 'Hamburg'], $eintrag->aenderungen['ort']);
    }

    public function test_detaildialog_fehlt_ohne_aenderungen(): void
    {
        $spender = $this->spender();
        $eintrag = $this->eintraege(ProtokollAktion::Angelegt)->first();

        Livewire::test(ListProtokolle::class)
            ->loadTable()
            ->assertTableActionHidden('details', $eintrag);
    }

    public function test_mitarbeiter_sieht_das_protokoll_nicht(): void
    {
        $this->actingAs(User::factory()->create(['ist_admin' => false]))
            ->get('/admin/protokoll')
            ->assertForbidden();
    }

    public function test_protokoll_laesst_sich_nicht_bearbeiten(): void
    {
        $this->spender();

        $this->assertFalse(ProtokollResource::canCreate());
        $this->assertFalse(ProtokollResource::canEdit(Protokolleintrag::first()));
        $this->assertFalse(ProtokollResource::canDelete(Protokolleintrag::first()));
    }
}
