<?php

namespace Tests\Feature;

use App\Services\ImportParserService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the spreadsheet reader path. The clipboard parser is unit tested
 * separately; this one goes through maatwebsite/excel and therefore needs a
 * file on disk.
 */
class ImportDateiTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempDateien = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDateien as $pfad) {
            @unlink($pfad);
        }

        parent::tearDown();
    }

    private function tempDatei(string $endung, string $inhalt = ''): string
    {
        $pfad = tempnam(sys_get_temp_dir(), 'import_') . '.' . $endung;

        file_put_contents($pfad, $inhalt);

        $this->tempDateien[] = $pfad;

        return $pfad;
    }

    public function test_csv_datei_wird_gelesen(): void
    {
        $pfad = $this->tempDatei('csv', implode("\n", [
            'An,Vorname1,Name,Straße,Plz,Ort,spende vom,Spende',
            'Herrn,Max,Mustermann,Hauptstr. 1,20095,Hamburg,01.03.2026,100',
            'Frau,Erika,Musterfrau,Nebenweg 2,30159,Hannover,02.03.2026,250',
        ]));

        $zeilen = app(ImportParserService::class)->ausDatei($pfad);

        $this->assertCount(2, $zeilen);
        $this->assertSame('Mustermann', $zeilen[0]['Name']);
        $this->assertSame('Hannover', $zeilen[1]['Ort']);
        $this->assertSame(2, $zeilen[0]['__zeile']);
    }

    public function test_xlsx_datei_wird_gelesen(): void
    {
        $pfad = $this->tempDatei('xlsx');

        config(['filesystems.disks.import_temp' => [
            'driver' => 'local',
            'root'   => dirname($pfad),
        ]]);

        // Build a real workbook so the reader, not just the CSV path, is covered.
        Excel::store(
            new class implements FromArray
            {
                public function array(): array
                {
                    return [
                        ['An', 'Vorname1', 'Name', 'Plz', 'Ort', 'spende vom', 'Spende'],
                        ['Herrn', 'Max', 'Mustermann', '20095', 'Hamburg', '01.03.2026', 100],
                    ];
                }
            },
            basename($pfad),
            'import_temp',
        );

        $zeilen = app(ImportParserService::class)->ausDatei($pfad);

        $this->assertCount(1, $zeilen);
        $this->assertSame('Mustermann', $zeilen[0]['Name']);
        $this->assertSame(100, $zeilen[0]['Spende']);
    }

    public function test_unlesbare_datei_wirft_eine_klare_meldung(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('konnte nicht gelesen werden');

        app(ImportParserService::class)->ausDatei('/pfad/gibt/es/nicht.xlsx');
    }
}
