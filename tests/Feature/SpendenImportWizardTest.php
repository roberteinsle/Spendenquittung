<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\ImportAktion;
use App\Filament\Pages\SpendenImport;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drives the import wizard the way the browser does. The plain render smoke
 * test cannot catch a broken step transition, because the parsing only runs in
 * the Step's afterValidation hook.
 */
class SpendenImportWizardTest extends TestCase
{
    use RefreshDatabase;

    private Foerderungszweck $zweck;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);

        $this->actingAs(User::factory()->create());

        $this->zweck = Foerderungszweck::create([
            'name'       => 'Bildung',
            'text'       => 'der Erziehung, Volks- und Berufsbildung',
            'aktiv'      => true,
            'sortierung' => 1,
        ]);
    }

    private const ZEILEN = "An\tVorname1\tName\tStraße\tPlz\tOrt\tspende vom\tSpende\n"
        . "Herrn\tMax\tMustermann\tHauptstr. 1\t20095\tHamburg\t01.03.2026\t100,00";

    /**
     * @return array<string, mixed>
     */
    private function schritt1(?string $zeilen = null): array
    {
        return [
            'quelle'              => 'text',
            'zwischenablage'      => $zeilen ?? self::ZEILEN,
            'foerderungszweck_id' => $this->zweck->id,
            'ankreuzfeld'         => AnkreuzfeldTyp::Unmittelbar->value,
            'ausstellungsdatum'   => '2026-04-01',
        ];
    }

    public function test_wizard_liest_zeilen_beim_schrittwechsel_ein(): void
    {
        $komponente = Livewire::test(SpendenImport::class)
            ->fillForm($this->schritt1())
            ->goToNextWizardStep()
            ->assertHasNoFormErrors();

        $zeilen = $komponente->get('data')['zeilen'];

        $this->assertCount(1, $zeilen);
        $this->assertSame('Mustermann', $zeilen[0]['nachname']);
        $this->assertSame(ImportAktion::NeuAnlegen->value, $zeilen[0]['aktion']);
    }

    public function test_wizard_bleibt_stehen_wenn_nichts_auswertbar_ist(): void
    {
        Livewire::test(SpendenImport::class)
            ->fillForm($this->schritt1('nur eine Kopfzeile ohne Daten'))
            ->goToNextWizardStep()
            ->assertHasFormErrors(['zwischenablage']);
    }

    public function test_wizard_verlangt_einen_foerderungszweck(): void
    {
        $daten = $this->schritt1();
        unset($daten['foerderungszweck_id']);

        Livewire::test(SpendenImport::class)
            ->fillForm($daten)
            ->goToNextWizardStep()
            ->assertHasFormErrors(['foerderungszweck_id']);
    }

    public function test_vollstaendiger_durchlauf_legt_die_bescheinigung_an(): void
    {
        Livewire::test(SpendenImport::class)
            ->fillForm($this->schritt1())
            ->goToNextWizardStep()
            ->goToNextWizardStep()
            ->call('importieren')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Spende::count());
        $this->assertSame(1, Spender::count());

        $spende = Spende::first();
        $this->assertSame('100.00', $spende->betrag);
        $this->assertSame('2026-03-01', $spende->spendendatum->toDateString());
        $this->assertSame('Mustermann', $spende->spender->nachname);
        $this->assertNotEmpty($spende->bescheinigungsnummer);
    }

    public function test_formular_wird_nach_dem_import_zurueckgesetzt(): void
    {
        $komponente = Livewire::test(SpendenImport::class)
            ->fillForm($this->schritt1())
            ->goToNextWizardStep()
            ->goToNextWizardStep()
            ->call('importieren');

        $this->assertSame([], $komponente->get('data')['zeilen']);
        $this->assertSame('', (string) $komponente->get('data')['zwischenablage']);
    }
}
