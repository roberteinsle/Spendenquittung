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
 * Smoke tests: every page of the admin panel has to render. These catch
 * Filament 3 class names that no longer exist in Filament 4, which otherwise
 * only show up as a 500 in the browser.
 */
class PanelSeitenTest extends TestCase
{
    use RefreshDatabase;

    private Spende $spende;

    /**
     * Die Smoke-Tests decken auch Einstellungen und Benutzerverwaltung ab.
     */
    private function verwalter(): User
    {
        return User::factory()->create(['ist_admin' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);

        $spender = Spender::create([
            'spendernummer' => '80002',
            'vorname'       => 'Erika',
            'nachname'      => 'Musterfrau',
            'plz'           => '20095',
            'ort'           => 'Hamburg',
            'aktiv'         => true,
        ]);

        $zweck = Foerderungszweck::create([
            'name'       => 'Bildung',
            'text'       => 'Förderung der Erziehung, Volks- und Berufsbildung',
            'aktiv'      => true,
            'sortierung' => 1,
        ]);

        $this->spende = Spende::create([
            'spender_id'          => $spender->id,
            'spendendatum'        => '2026-03-01',
            'betrag'              => 100.00,
            'foerderungszweck_id' => $zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listenUndFormulare(): array
    {
        return [
            'Dashboard'                => ['filament.admin.pages.dashboard'],
            'Einstellungen'            => ['filament.admin.pages.einstellungen'],
            'Bescheinigungen'          => ['filament.admin.resources.spendes.index'],
            'Bescheinigung anlegen'    => ['filament.admin.resources.spendes.create'],
            'Spender'                  => ['filament.admin.resources.spenders.index'],
            'Spender anlegen'          => ['filament.admin.resources.spenders.create'],
            'Förderungszwecke'         => ['filament.admin.resources.foerderungszwecks.index'],
            'Förderungszweck anlegen'  => ['filament.admin.resources.foerderungszwecks.create'],
            'Benutzer'                 => ['filament.admin.resources.users.index'],
            'Benutzer anlegen'         => ['filament.admin.resources.users.create'],
        ];
    }

    #[DataProvider('listenUndFormulare')]
    public function test_seite_rendert(string $route): void
    {
        $this->actingAs($this->verwalter())
            ->get(route($route))
            ->assertOk();
    }

    public function test_startseite_leitet_ins_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_bescheinigung_bearbeiten_mit_versandprotokoll_rendert(): void
    {
        $this->actingAs($this->verwalter())
            ->get(route('filament.admin.resources.spendes.edit', $this->spende))
            ->assertOk();
    }

    public function test_spender_bearbeiten_rendert(): void
    {
        $this->actingAs($this->verwalter())
            ->get(route('filament.admin.resources.spenders.edit', $this->spende->spender))
            ->assertOk();
    }

    public function test_foerderungszweck_bearbeiten_rendert(): void
    {
        $this->actingAs($this->verwalter())
            ->get(route('filament.admin.resources.foerderungszwecks.edit', $this->spende->foerderungszweck))
            ->assertOk();
    }
}
