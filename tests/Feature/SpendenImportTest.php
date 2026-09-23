<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\ImportAktion;
use App\Exceptions\ImportFehlgeschlagen;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use App\Services\ImportParserService;
use App\Services\ImportVorbereitungService;
use App\Services\SpendenImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendenImportTest extends TestCase
{
    use RefreshDatabase;

    private Foerderungszweck $zweck;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);

        $this->zweck = Foerderungszweck::create([
            'name'       => 'Bildung',
            'text'       => 'der Erziehung, Volks- und Berufsbildung',
            'aktiv'      => true,
            'sortierung' => 1,
        ]);
    }

    private function vorgaben(): array
    {
        return [
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar->value,
            'ausstellungsdatum'   => '2026-04-01',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vorschlaege(string $text): array
    {
        $roh = app(ImportParserService::class)->ausText($text);

        return app(ImportVorbereitungService::class)->bereiteVor($roh);
    }

    // ─── Vorbereitung ───────────────────────────────────────────────

    public function test_unbekannter_spender_wird_zum_anlegen_vorgeschlagen(): void
    {
        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tStraße\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\tHauptstr. 1\t20095\tHamburg\t01.03.2026\t100,00"
        );

        $this->assertCount(1, $zeilen);
        $this->assertSame(ImportAktion::NeuAnlegen->value, $zeilen[0]['aktion']);
        $this->assertNull($zeilen[0]['spender_id']);
        $this->assertSame('Mustermann', $zeilen[0]['nachname']);
        $this->assertSame('2026-03-01', $zeilen[0]['spendendatum']);
        $this->assertSame(100.0, $zeilen[0]['betrag']);
    }

    public function test_bekannter_spender_wird_zugeordnet(): void
    {
        $spender = Spender::create([
            'vorname'  => 'Max',
            'nachname' => 'Mustermann',
            'plz'      => '20095',
            'ort'      => 'Hamburg',
            'aktiv'    => true,
        ]);

        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t100,00"
        );

        $this->assertSame(ImportAktion::Verwenden->value, $zeilen[0]['aktion']);
        $this->assertSame($spender->id, $zeilen[0]['spender_id']);
    }

    public function test_betrag_in_worten_wird_ergaenzt(): void
    {
        $zeilen = $this->vorschlaege(
            "Name\tPlz\tspende vom\tSpende\nMustermann\t20095\t01.03.2026\t100,00"
        );

        $this->assertNotSame('', $zeilen[0]['betrag_in_worten']);
    }

    public function test_zeile_ohne_betrag_wird_zum_ueberspringen_markiert(): void
    {
        $zeilen = $this->vorschlaege(
            "Name\tPlz\tspende vom\tSpende\nMustermann\t20095\t01.03.2026\t/"
        );

        $this->assertSame(ImportAktion::Ueberspringen->value, $zeilen[0]['aktion']);
        $this->assertStringContainsString('Betrag', $zeilen[0]['hinweis']);
    }

    public function test_zeile_ohne_datum_wird_zum_ueberspringen_markiert(): void
    {
        $zeilen = $this->vorschlaege(
            "Name\tPlz\tspende vom\tSpende\nMustermann\t20095\t\t100,00"
        );

        $this->assertSame(ImportAktion::Ueberspringen->value, $zeilen[0]['aktion']);
        $this->assertStringContainsString('Spendendatum', $zeilen[0]['hinweis']);
    }

    public function test_bereits_erfasste_spende_wird_als_dublette_erkannt(): void
    {
        $spender = Spender::create([
            'vorname'  => 'Max',
            'nachname' => 'Mustermann',
            'plz'      => '20095',
            'ort'      => 'Hamburg',
            'aktiv'    => true,
        ]);

        Spende::create([
            'spender_id'          => $spender->id,
            'spendendatum'        => '2026-03-01',
            'betrag'              => 100.00,
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar,
        ]);

        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t100,00"
        );

        $this->assertSame(ImportAktion::Ueberspringen->value, $zeilen[0]['aktion']);
        $this->assertStringContainsString('Bereits erfasst', $zeilen[0]['hinweis']);
    }

    public function test_bereits_importierte_lfd_nr_wird_als_dublette_erkannt(): void
    {
        $spender = Spender::create(['nachname' => 'Andere', 'aktiv' => true]);

        Spende::create([
            'spender_id'          => $spender->id,
            'spendendatum'        => '2020-01-01',
            'betrag'              => 5.00,
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar,
            'alte_lfd_nr'         => '4711',
        ]);

        $zeilen = $this->vorschlaege(
            "Name\tPlz\tspende vom\tSpende\tlfd. Nr.\nMustermann\t20095\t01.03.2026\t100,00\t4711"
        );

        $this->assertSame(ImportAktion::Ueberspringen->value, $zeilen[0]['aktion']);
        $this->assertStringContainsString('Bereits erfasst', $zeilen[0]['hinweis']);
    }

    // ─── Import ─────────────────────────────────────────────────────

    public function test_import_legt_spender_und_bescheinigung_an(): void
    {
        $this->actingAs(User::factory()->create());

        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tStraße\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\tHauptstr. 1\t20095\tHamburg\t01.03.2026\t100,00"
        );

        $ergebnis = app(SpendenImportService::class)->importiere($zeilen, $this->vorgaben());

        $this->assertSame(1, $ergebnis['erstellt']);
        $this->assertSame(1, $ergebnis['neue_spender']);
        $this->assertSame(0, $ergebnis['uebersprungen']);

        $spender = Spender::firstWhere('nachname', 'Mustermann');
        $this->assertNotNull($spender);
        $this->assertSame('Hauptstr. 1', $spender->strasse);
        $this->assertNotEmpty($spender->spendernummer);

        $spende = Spende::first();
        $this->assertSame($spender->id, $spende->spender_id);
        $this->assertSame('100.00', $spende->betrag);
        $this->assertSame($this->zweck->id, $spende->foerderungszweck_id);
        $this->assertSame(AnkreuzfeldTyp::Unmittelbar, $spende->ankreuzfeld);
        $this->assertNotEmpty($spende->bescheinigungsnummer);
        $this->assertSame('2026-04-01', $spende->ausstellungsdatum->toDateString());
    }

    public function test_uebersprungene_zeilen_erzeugen_nichts(): void
    {
        $zeilen = $this->vorschlaege(
            "Name\tPlz\tspende vom\tSpende\nMustermann\t20095\t01.03.2026\t/"
        );

        $ergebnis = app(SpendenImportService::class)->importiere($zeilen, $this->vorgaben());

        $this->assertSame(0, $ergebnis['erstellt']);
        $this->assertSame(1, $ergebnis['uebersprungen']);
        $this->assertSame(0, Spende::count());
        $this->assertSame(0, Spender::count());
    }

    public function test_import_ordnet_vorhandenem_spender_zu_ohne_neuen_anzulegen(): void
    {
        $spender = Spender::create([
            'vorname'  => 'Max',
            'nachname' => 'Mustermann',
            'plz'      => '20095',
            'ort'      => 'Hamburg',
            'aktiv'    => true,
        ]);

        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t250,00"
        );

        $ergebnis = app(SpendenImportService::class)->importiere($zeilen, $this->vorgaben());

        $this->assertSame(1, $ergebnis['erstellt']);
        $this->assertSame(0, $ergebnis['neue_spender']);
        $this->assertSame(1, Spender::count());
        $this->assertSame($spender->id, Spende::first()->spender_id);
    }

    public function test_fehlerhafte_zeile_rollt_den_gesamten_stapel_zurueck(): void
    {
        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t100,00\n"
            . "Frau\tErika\tMusterfrau\t20095\tHamburg\t02.03.2026\t200,00"
        );

        // Force the second row to fail: an action that requires an existing
        // donor, but without a donor id.
        $zeilen[1]['aktion']     = ImportAktion::Verwenden->value;
        $zeilen[1]['spender_id'] = null;

        try {
            app(SpendenImportService::class)->importiere($zeilen, $this->vorgaben());
            $this->fail('ImportFehlgeschlagen wurde nicht geworfen.');
        } catch (ImportFehlgeschlagen $e) {
            $this->assertCount(1, $e->fehler);
        }

        // Nothing at all may survive – otherwise a Bescheinigungsnummer would
        // have been burned on a batch the user never confirmed as complete.
        $this->assertSame(0, Spende::count());
        $this->assertSame(0, Spender::count());
    }

    public function test_mehrere_zeilen_bekommen_fortlaufende_nummern(): void
    {
        $zeilen = $this->vorschlaege(
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende\n"
            . "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t100,00\n"
            . "Frau\tErika\tMusterfrau\t30159\tHannover\t02.03.2026\t200,00"
        );

        $ergebnis = app(SpendenImportService::class)->importiere($zeilen, $this->vorgaben());

        $this->assertSame(2, $ergebnis['erstellt']);
        $this->assertSame(2, $ergebnis['neue_spender']);

        $nummern = Spende::pluck('bescheinigungsnummer');
        $this->assertCount(2, $nummern->unique());
    }

    // ─── Seite ──────────────────────────────────────────────────────

    public function test_importseite_rendert(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.pages.spenden-import'))
            ->assertOk();
    }
}
