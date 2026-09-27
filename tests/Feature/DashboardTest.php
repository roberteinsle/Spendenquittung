<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Filament\Widgets\LetzteBescheinigungen;
use App\Filament\Widgets\LetzteSpender;
use App\Filament\Widgets\Spitzenwerte;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Foerderungszweck $zweck;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);

        $this->zweck = Foerderungszweck::create([
            'name' => 'Bildung',
            'text' => 'Förderung der Bildung',
            'aktiv' => true,
            'sortierung' => 1,
        ]);
    }

    private function spender(int $nr): Spender
    {
        return Spender::create([
            'spendernummer' => '8000'.$nr,
            'vorname' => 'Vorname'.$nr,
            'nachname' => 'Nachname'.$nr,
            'ort' => 'Hamburg',
            'aktiv' => true,
        ]);
    }

    private function spende(Spender $spender, float $betrag): Spende
    {
        return Spende::create([
            'spender_id' => $spender->id,
            'spendendatum' => '2026-03-01',
            'betrag' => $betrag,
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld' => AnkreuzfeldTyp::Unmittelbar,
        ]);
    }

    public function test_bescheinigungs_widget_zeigt_die_letzten_fuenf(): void
    {
        $spender = $this->spender(1);
        $alle = collect(range(1, 7))->map(fn (int $i) => $this->spende($spender, $i * 10));

        Livewire::actingAs(User::factory()->create())
            ->test(LetzteBescheinigungen::class)
            ->loadTable()
            ->assertCanSeeTableRecords($alle->slice(2))   // die 5 jüngsten
            ->assertCanNotSeeTableRecords($alle->take(2)); // die 2 ältesten
    }

    public function test_spender_widget_zeigt_die_letzten_fuenf(): void
    {
        $alle = collect(range(1, 7))->map(fn (int $i) => $this->spender($i));

        Livewire::actingAs(User::factory()->create())
            ->test(LetzteSpender::class)
            ->loadTable()
            ->assertCanSeeTableRecords($alle->slice(2))
            ->assertCanNotSeeTableRecords($alle->take(2));
    }

    public function test_bescheinigungen_stehen_links_von_den_spendern(): void
    {
        $this->assertLessThan(
            LetzteSpender::getSort(),
            LetzteBescheinigungen::getSort(),
        );
    }

    public function test_dashboard_bindet_beide_widgets_ein_und_keine_filament_werbung(): void
    {
        $spender = $this->spender(1);
        $this->spende($spender, 70.70);

        // Die Widgets laden ihre Tabelle nachträglich, im ersten HTML steht
        // deshalb nur die Livewire-Komponente.
        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Spitzenwerte::class)
            ->assertSeeLivewire(LetzteBescheinigungen::class)
            ->assertSeeLivewire(LetzteSpender::class)
            ->assertDontSeeLivewire(FilamentInfoWidget::class)
            ->assertDontSeeLivewire(AccountWidget::class);
    }

    public function test_widgets_tragen_ihre_ueberschrift(): void
    {
        $nutzer = User::factory()->create();

        Livewire::actingAs($nutzer)
            ->test(LetzteBescheinigungen::class)
            ->loadTable()
            ->assertSee('Letzte Bescheinigungen');

        Livewire::actingAs($nutzer)
            ->test(LetzteSpender::class)
            ->loadTable()
            ->assertSee('Letzte Spender');
    }
}
