<?php

namespace Tests\Feature;

use App\Filament\Pages\Einstellungen;
use App\Filament\Resources\Users\UserResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BerechtigungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['name' => 'Chefin', 'ist_admin' => true]);
    }

    private function mitarbeiter(): User
    {
        return User::factory()->create(['name' => 'Mitarbeiterin', 'ist_admin' => false]);
    }

    public function test_admin_darf_die_einstellungen_oeffnen(): void
    {
        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.einstellungen'))
            ->assertOk();
    }

    public function test_mitarbeiter_kommt_nicht_in_die_einstellungen(): void
    {
        $this->actingAs($this->mitarbeiter())
            ->get(route('filament.admin.pages.einstellungen'))
            ->assertForbidden();
    }

    public function test_mitarbeiter_kommt_nicht_in_die_benutzerverwaltung(): void
    {
        $this->actingAs($this->mitarbeiter())
            ->get(route('filament.admin.resources.users.index'))
            ->assertForbidden();
    }

    public function test_mitarbeiter_kann_kein_konto_anlegen(): void
    {
        $this->actingAs($this->mitarbeiter())
            ->get(route('filament.admin.resources.users.create'))
            ->assertForbidden();
    }

    public function test_navigation_verrät_die_einstellungen_nicht(): void
    {
        $this->assertFalse(Einstellungen::canAccess());
        $this->assertFalse(UserResource::canAccess());

        $this->actingAs($this->mitarbeiter());
        $this->assertFalse(Einstellungen::canAccess());
        $this->assertFalse(UserResource::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(Einstellungen::canAccess());
        $this->assertTrue(UserResource::canAccess());
    }

    public function test_mitarbeiter_behaelt_zugriff_auf_die_taegliche_arbeit(): void
    {
        $nutzer = $this->mitarbeiter();

        foreach ([
            'filament.admin.pages.dashboard',
            'filament.admin.resources.spendes.index',
            'filament.admin.resources.spenders.index',
            'filament.admin.resources.foerderungszwecks.index',
        ] as $route) {
            $this->actingAs($nutzer)->get(route($route))->assertOk();
        }
    }

    public function test_marke_zeigt_die_herzfigur_wenn_hinterlegt(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/herz.png', 'PNG');
        Setting::set('herzfigur_pfad', 'logos/herz.png');

        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('logos/herz.png', escape: false)
            ->assertSee('Spendenquittung');
    }

    public function test_marke_zeigt_ohne_herzfigur_nur_den_namen(): void
    {
        Storage::fake('public');
        Setting::set('herzfigur_pfad', '');

        $this->actingAs($this->admin())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee('Spendenquittung')
            ->assertDontSee('<img src="/storage/logos', escape: false);
    }
}
