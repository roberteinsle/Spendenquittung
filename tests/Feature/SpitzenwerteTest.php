<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Filament\Widgets\LetzteBescheinigungen;
use App\Filament\Widgets\Spitzenwerte;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class SpitzenwerteTest extends TestCase
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

    private function spender(string $nachname): Spender
    {
        return Spender::create([
            'spendernummer' => '8'.random_int(1000, 9999),
            'vorname' => 'Vorname',
            'nachname' => $nachname,
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

    private function widget(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(Spitzenwerte::class);
    }

    public function test_zeigt_den_spender_mit_der_hoechsten_einzelspende(): void
    {
        $klein = $this->spender('Kleinspender');
        $gross = $this->spender('Grossspender');

        // Viele kleine Spenden schlagen eine grosse Einzelspende nicht.
        foreach (range(1, 5) as $i) {
            $this->spende($klein, 100);
        }
        $this->spende($gross, 5000);

        $this->widget()
            ->assertSee('Höchste Einzelspende')
            ->assertSee('5.000,00 €')
            ->assertSee('Vorname Grossspender');
    }

    public function test_zeigt_den_spender_mit_den_meisten_spenden(): void
    {
        $viele = $this->spender('Vielspender');
        $einer = $this->spender('Einmalspender');

        foreach (range(1, 4) as $i) {
            $this->spende($viele, 10);
        }
        $this->spende($einer, 9999);

        $this->widget()
            ->assertSee('Meiste Spenden')
            ->assertSee('4 Spenden')
            ->assertSee('Vorname Vielspender');
    }

    public function test_einzahl_bei_genau_einer_spende(): void
    {
        $this->spende($this->spender('Einmalspender'), 50);

        $this->widget()->assertSee('1 Spende');
    }

    public function test_geloeschte_spenden_zaehlen_nicht_mit(): void
    {
        $spender = $this->spender('Korrigiert');
        $this->spende($spender, 10);
        $this->spende($spender, 9999)->delete();

        $andere = $this->spender('Aktuell');
        $this->spende($andere, 500);

        $this->widget()
            // Die gelöschte 9.999-Spende darf nicht mehr Spitzenreiter sein.
            ->assertSee('500,00 €')
            ->assertDontSee('9.999,00 €');
    }

    public function test_ohne_daten_bleibt_das_widget_ruhig(): void
    {
        $this->widget()
            ->assertSee('Höchste Einzelspende')
            ->assertSee('Meiste Spenden')
            ->assertSee('Noch keine Spende erfasst');
    }

    public function test_steht_ueber_den_beiden_listen(): void
    {
        $this->assertLessThan(LetzteBescheinigungen::getSort(), Spitzenwerte::getSort());
    }
}
