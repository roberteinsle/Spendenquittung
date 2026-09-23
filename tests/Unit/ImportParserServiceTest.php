<?php

namespace Tests\Unit;

use App\Services\ImportParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportParserServiceTest extends TestCase
{
    private ImportParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ImportParserService;
    }

    public function test_tab_getrennter_text_wird_nach_kopfzeile_geschluesselt(): void
    {
        $text = implode("\n", [
            "An\tVorname1\tName\tPlz\tOrt\tspende vom\tSpende",
            "Herrn\tMax\tMustermann\t20095\tHamburg\t01.03.2026\t100,00",
        ]);

        $zeilen = $this->parser->ausText($text);

        $this->assertCount(1, $zeilen);
        $this->assertSame('Herrn', $zeilen[0]['An']);
        $this->assertSame('Mustermann', $zeilen[0]['Name']);
        $this->assertSame('100,00', $zeilen[0]['Spende']);
    }

    public function test_quellzeilennummer_wird_mitgefuehrt(): void
    {
        $text = "Name\tSpende\nMustermann\t100\nMusterfrau\t200";

        $zeilen = $this->parser->ausText($text);

        $this->assertSame(2, $zeilen[0]['__zeile']);
        $this->assertSame(3, $zeilen[1]['__zeile']);
    }

    public function test_leere_zeilen_werden_uebersprungen(): void
    {
        $text = "Name\tSpende\n\t\nMustermann\t100\n\t";

        $zeilen = $this->parser->ausText($text);

        $this->assertCount(1, $zeilen);
        $this->assertSame('Mustermann', $zeilen[0]['Name']);
    }

    #[DataProvider('trennerVarianten')]
    public function test_trenner_wird_erkannt(string $trenner): void
    {
        $text = "Name{$trenner}Spende\nMustermann{$trenner}100";

        $zeilen = $this->parser->ausText($text);

        $this->assertSame('Mustermann', $zeilen[0]['Name']);
        $this->assertSame('100', $zeilen[0]['Spende']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trennerVarianten(): array
    {
        return [
            'Tabulator' => ["\t"],
            'Semikolon' => [';'],
            'Komma'     => [','],
        ];
    }

    public function test_leere_kopfzellen_bekommen_den_spaltenbuchstaben(): void
    {
        // Columns L, M and N of the historical sheet carry notes but no header.
        $text = "Name\tSpende\t\t\nMustermann\t100\tNotiz A\tNotiz B";

        $zeilen = $this->parser->ausText($text);

        $this->assertSame('Notiz A', $zeilen[0]['C']);
        $this->assertSame('Notiz B', $zeilen[0]['D']);
    }

    public function test_leerer_text_ergibt_keine_zeilen(): void
    {
        $this->assertSame([], $this->parser->ausText("   \n  "));
    }

    public function test_windows_zeilenumbrueche_werden_verarbeitet(): void
    {
        $zeilen = $this->parser->ausText("Name\tSpende\r\nMustermann\t100");

        $this->assertCount(1, $zeilen);
        $this->assertSame('Mustermann', $zeilen[0]['Name']);
    }

    public function test_doppelte_kopfnamen_ueberschreiben_sich_nicht(): void
    {
        $text = "Name\tName\nErster\tZweiter";

        $zeilen = $this->parser->ausText($text);

        $this->assertSame('Erster', $zeilen[0]['Name']);
        $this->assertSame('Zweiter', $zeilen[0]['Name_1']);
    }
}
