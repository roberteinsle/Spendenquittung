<?php

namespace Tests\Feature;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\Anrede;
use App\Enums\ExportFormat;
use App\Exports\SpendenExport;
use App\Exports\SpenderExport;
use App\Filament\Resources\Spendes\Pages\ListSpendes;
use App\Imports\RohdatenImport;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
    }

    private function spende(string $nachname = 'Mustermann', float $betrag = 1234.50): Spende
    {
        $spender = Spender::create([
            'spendernummer' => '8000'.random_int(1000, 9999),
            'anrede' => Anrede::Herrn,
            'vorname' => 'Max',
            'nachname' => $nachname,
            'strasse' => 'Musterweg 1',
            'plz' => '20095',
            'ort' => 'Hamburg',
            'aktiv' => true,
        ]);

        $zweck = Foerderungszweck::firstOrCreate(
            ['name' => 'Bildung'],
            ['text' => 'Förderung der Bildung', 'aktiv' => true, 'sortierung' => 1],
        );

        return Spende::create([
            'spender_id' => $spender->id,
            'spendendatum' => '2026-03-01',
            'betrag' => $betrag,
            'foerderungszweck_id' => $zweck->id,
            'ankreuzfeld' => AnkreuzfeldTyp::Unmittelbar,
        ])->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function zeilen(Spende ...$spenden): array
    {
        return array_map(fn (Spende $s): array => SpendenExport::zeile($s), $spenden);
    }

    private function erzeuge(ExportFormat $format, array $zeilen): string
    {
        return app(ExportService::class)->erzeuge(
            format: $format,
            spalten: SpendenExport::spalten(),
            zeilen: $zeilen,
            wurzel: SpendenExport::xmlWurzel(),
            eintrag: SpendenExport::xmlEintrag(),
        );
    }

    public function test_markdown_ist_eine_tabelle_mit_deutschen_zahlen(): void
    {
        $spende = $this->spende();

        $md = $this->erzeuge(ExportFormat::Markdown, $this->zeilen($spende));

        $this->assertStringContainsString('| Bescheinigungsnummer |', $md);
        $this->assertStringContainsString('| --- |', $md);
        $this->assertStringContainsString('Mustermann', $md);
        $this->assertStringContainsString('1.234,50', $md);
        $this->assertStringContainsString('01.03.2026', $md);
    }

    public function test_markdown_zerbricht_nicht_an_einem_pipe_im_wert(): void
    {
        $spende = $this->spende(nachname: 'Muster | mann');

        $md = $this->erzeuge(ExportFormat::Markdown, $this->zeilen($spende));

        // Maskierte Pipes zählen nicht als Spaltentrenner – jede Zeile muss
        // also gleich viele unmaskierte haben.
        $zeilen = array_values(array_filter(explode("\n", $md)));
        $spalten = array_map(
            fn (string $z): int => preg_match_all('/(?<!\\\\)\|/', $z),
            $zeilen,
        );

        $this->assertSame([$spalten[0]], array_values(array_unique($spalten)));
        $this->assertStringContainsString('Muster \\| mann', $md);
    }

    public function test_xml_ist_wohlgeformt_und_maschinenlesbar(): void
    {
        $spende = $this->spende();

        $xml = $this->erzeuge(ExportFormat::Xml, $this->zeilen($spende));

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'XML ist nicht wohlgeformt');
        $this->assertSame('bescheinigungen', $doc->getName());
        $this->assertSame('1', (string) $doc['anzahl']);
        $this->assertSame($spende->bescheinigungsnummer, (string) $doc->bescheinigung->bescheinigungsnummer);
        // Punkt als Dezimaltrenner, damit sich der Wert weiterverarbeiten lässt.
        $this->assertSame('1234.50', (string) $doc->bescheinigung->betrag);
    }

    public function test_xml_maskiert_sonderzeichen(): void
    {
        $spende = $this->spende(nachname: 'Meier & Söhne <GmbH>');

        $xml = $this->erzeuge(ExportFormat::Xml, $this->zeilen($spende));

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'XML ist nicht wohlgeformt');
        $this->assertSame('Max Meier & Söhne <GmbH>', (string) $doc->bescheinigung->spender);
    }

    public function test_excel_liefert_eine_lesbare_xlsx_datei(): void
    {
        $spende = $this->spende();

        $xlsx = $this->erzeuge(ExportFormat::Excel, $this->zeilen($spende));

        $pfad = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        file_put_contents($pfad, $xlsx);

        $blatt = Excel::toArray(new RohdatenImport, $pfad)[0];
        unlink($pfad);

        $this->assertSame('Bescheinigungsnummer', $blatt[0][0]);
        $this->assertSame($spende->bescheinigungsnummer, (string) $blatt[1][0]);
        // Betrag als Zahl, nicht als Text – sonst lässt sich nicht summieren.
        $this->assertSame(1234.5, (float) $blatt[1][2]);
    }

    public function test_spender_export_kennt_die_ja_nein_felder(): void
    {
        $this->spende();
        $spender = Spender::first();

        $zeile = SpenderExport::zeile($spender);
        $md = app(ExportService::class)->erzeuge(
            ExportFormat::Markdown,
            SpenderExport::spalten(),
            [$zeile],
            SpenderExport::xmlWurzel(),
            SpenderExport::xmlEintrag(),
        );

        $this->assertStringContainsString('| ja |', $md);
    }

    public function test_export_aktion_beruecksichtigt_suche_und_filter(): void
    {
        $this->spende('Behalten');
        $this->spende('Weglassen');

        $antwort = Livewire::actingAs(User::factory()->create())
            ->test(ListSpendes::class)
            ->loadTable()
            ->searchTable('Behalten')
            ->callTableAction('export', data: ['format' => ExportFormat::Markdown->value])
            ->assertHasNoActionErrors();

        $inhalt = $this->inhaltDesDownloads($antwort);

        $this->assertStringContainsString('Behalten', $inhalt);
        $this->assertStringNotContainsString('Weglassen', $inhalt);
    }

    public function test_export_aktion_liefert_den_passenden_dateinamen(): void
    {
        $this->spende();

        $antwort = Livewire::actingAs(User::factory()->create())
            ->test(ListSpendes::class)
            ->loadTable()
            ->callTableAction('export', data: ['format' => ExportFormat::Xml->value]);

        $name = $antwort->effects['download']['name'] ?? '';

        $this->assertSame('bescheinigungen-'.now()->format('Y-m-d').'.xml', $name);
    }

    /**
     * Livewire reicht den Download base64-kodiert in den Effekten zurück.
     */
    private function inhaltDesDownloads(object $antwort): string
    {
        $download = $antwort->effects['download'] ?? null;

        $this->assertNotNull($download, 'Die Aktion hat keinen Download ausgelöst.');

        return base64_decode($download['content']);
    }
}
