<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Oberfläche war auf dem Server englisch, weil APP_LOCALE nicht bis in den
 * Container durchgereicht wurde und der Default in config/app.php "en" war.
 */
class SpracheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
    }

    private function datenAnlegen(): void
    {
        $spender = Spender::create([
            'spendernummer' => '80001',
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'aktiv' => true,
        ]);

        $zweck = Foerderungszweck::create([
            'name' => 'Bildung',
            'text' => 'Förderung der Bildung',
            'aktiv' => true,
            'sortierung' => 1,
        ]);

        Spende::create([
            'spender_id' => $spender->id,
            'spendendatum' => '2026-03-01',
            'betrag' => 70.70,
            'foerderungszweck_id' => $zweck->id,
            'ankreuzfeld' => AnkreuzfeldTyp::Unmittelbar,
        ]);
    }

    public function test_die_app_laeuft_auf_deutsch_auch_ohne_gesetzte_umgebungsvariable(): void
    {
        $this->assertSame('de', config('app.locale'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listen(): array
    {
        return [
            'Spender' => ['filament.admin.resources.spender.index'],
            'Bescheinigungen' => ['filament.admin.resources.bescheinigungen.index'],
            'Förderungszwecke' => ['filament.admin.resources.foerderungszwecke.index'],
            'Benutzer' => ['filament.admin.resources.benutzer.index'],
        ];
    }

    #[DataProvider('listen')]
    public function test_liste_zeigt_deutsche_bedienelemente(string $route): void
    {
        $this->datenAnlegen();

        $this->actingAs(User::factory()->create(['ist_admin' => true]))
            ->get(route($route))
            ->assertOk()
            ->assertSee('Liste')
            ->assertSee('Neu')
            // Gezielt das Attribut, nicht das blosse Wort: "Search" steckt auch
            // in internen Livewire-Eigenschaften wie tableSearch.
            ->assertSee('placeholder="Suchen"', escape: false)
            ->assertDontSee('placeholder="Search"', escape: false);
    }

    /**
     * Die Bausteine, die Filament selbst übersetzt. Paginierung und
     * Trefferanzeige lädt die Tabelle nach, im ersten HTML stehen sie noch
     * nicht – deshalb hier direkt gegen die Übersetzung geprüft.
     *
     * @return array<string, array{string, string}>
     */
    public static function bausteine(): array
    {
        return [
            'Breadcrumb' => ['filament-panels::resources/pages/list-records.breadcrumb', 'Liste'],
            'Neu-Knopf' => ['filament-actions::create.single.label', 'Neu'],
            'Suchfeld' => ['filament-tables::table.fields.search.label', 'Suchen'],
            'Pro Seite' => ['filament::components/pagination.fields.records_per_page.label', 'pro Seite'],
        ];
    }

    #[DataProvider('bausteine')]
    public function test_baustein_ist_uebersetzt(string $schluessel, string $erwartet): void
    {
        $this->assertSame($erwartet, __($schluessel));
    }

    public function test_trefferanzeige_ist_deutsch(): void
    {
        $this->assertSame(
            'Zeige 1 Ergebnis',
            trans_choice('filament::components/pagination.overview', 1, ['first' => 1, 'last' => 1, 'total' => 1]),
        );

        $this->assertSame(
            'Zeige 1 bis 5 von 5 Ergebnissen',
            trans_choice('filament::components/pagination.overview', 5, ['first' => 1, 'last' => 5, 'total' => 5]),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adressen(): array
    {
        return [
            'Spender' => ['/admin/spender'],
            'Bescheinigungen' => ['/admin/bescheinigungen'],
            'Förderungszwecke' => ['/admin/foerderungszwecke'],
            'Benutzer' => ['/admin/benutzer'],
        ];
    }

    #[DataProvider('adressen')]
    public function test_adressen_sind_deutsch(string $pfad): void
    {
        $this->actingAs(User::factory()->create(['ist_admin' => true]))
            ->get($pfad)
            ->assertOk();
    }

    public function test_alte_englische_adresse_gibt_es_nicht_mehr(): void
    {
        $this->actingAs(User::factory()->create(['ist_admin' => true]))
            ->get('/admin/spenders')
            ->assertNotFound();
    }
}
