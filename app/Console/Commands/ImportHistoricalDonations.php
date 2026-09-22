<?php

namespace App\Console\Commands;

use App\Enums\SpendeStatus;
use App\Models\Foerderungszweck;
use App\Models\Spende;
use App\Models\Spender;
use App\Services\BescheinigungsnummerService;
use App\Services\BetragInWortenService;
use App\Services\ImportNormalisierungService;
use App\Services\SpendernummerService;
use App\Services\SpenderMatchingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

#[Signature('import:historical {--file=storage/app/import_source.xlsx : Path to the Excel file} {--dry-run : Simulate without saving} {--skip-existing : Skip rows whose alte_lfd_nr already exists}')]
#[Description('Einmaliger historischer Import der Spenden-Excel (ca. 1750 Zeilen, 2010-2026)')]
class ImportHistoricalDonations extends Command
{
    public function handle(
        ImportNormalisierungService $normalisierer,
        SpenderMatchingService $matcher,
        BescheinigungsnummerService $bescheinigungsNr,
        SpendernummerService $spenderNr,
        BetragInWortenService $betragInWorten,
    ): int {
        $filePath  = $this->option('file');
        $isDryRun  = $this->option('dry-run');
        $skipExisting = $this->option('skip-existing');

        if (! file_exists(base_path($filePath))) {
            $this->error("Datei nicht gefunden: {$filePath}");
            $this->line("Bitte die Excel-Datei nach storage/app/import_source.xlsx kopieren.");
            return self::FAILURE;
        }

        $this->info("Lade Excel-Datei: {$filePath}");

        // Load all rows from sheet
        $rows = Excel::toCollection(null, base_path($filePath))->first();

        if ($rows === null || $rows->isEmpty()) {
            $this->error("Keine Daten in der Excel-Datei gefunden.");
            return self::FAILURE;
        }

        // First row = headers
        $headers   = $rows->first()->map(fn ($h) => strtolower(trim((string) $h)))->toArray();
        $dataRows  = $rows->skip(1);

        $this->info("Gefundene Zeilen: " . $dataRows->count());

        // Load default Foerderungszweck
        $defaultZweck = Foerderungszweck::aktiv()->first();
        if (! $defaultZweck) {
            $this->error("Kein aktiver Förderungszweck vorhanden. Seeder ausführen: php artisan db:seed");
            return self::FAILURE;
        }

        $stats = ['gesamt' => 0, 'spender_neu' => 0, 'spender_gematcht' => 0, 'spenden' => 0, 'fehler' => 0, 'uebersprungen' => 0];

        $bar = $this->output->createProgressBar($dataRows->count());
        $bar->start();

        $chunks = $dataRows->chunk(config('spendenquittung.import_chunk_size', 200));

        foreach ($chunks as $chunk) {
            foreach ($chunk as $rawRow) {
                $stats['gesamt']++;

                // Build associative array using headers
                $row = array_combine($headers, $rawRow->toArray());

                $zeile = $normalisierer->normalisiereZeile($row);

                if ($zeile === null) {
                    $stats['uebersprungen']++;
                    $bar->advance();
                    continue;
                }

                // Skip if no betrag (historical rows without amount)
                if ($zeile['betrag'] === null || $zeile['betrag'] <= 0) {
                    $stats['uebersprungen']++;
                    $bar->advance();
                    continue;
                }

                // Skip if lfd_nr already imported
                if ($skipExisting && $zeile['alte_lfd_nr'] !== '') {
                    $exists = Spende::where('alte_lfd_nr', $zeile['alte_lfd_nr'])->exists();
                    if ($exists) {
                        $stats['uebersprungen']++;
                        $bar->advance();
                        continue;
                    }
                }

                try {
                    if (! $isDryRun) {
                        DB::transaction(function () use ($zeile, $matcher, $spenderNr, $bescheinigungsNr, $betragInWorten, $defaultZweck, &$stats) {
                            // Find or create donor
                            if ($zeile['nachname'] !== '' || $zeile['firma'] !== null) {
                                $matchResult = $matcher->finde($zeile);

                                if ($matchResult['empfehlung'] === 'verwenden' && $matchResult['spender']) {
                                    $spender = $matchResult['spender'];
                                    $stats['spender_gematcht']++;
                                } else {
                                    // Create new donor
                                    $spender = Spender::create([
                                        'spendernummer' => $spenderNr->generiere(),
                                        'anrede'        => $zeile['anrede'],
                                        'firma'         => $zeile['firma'],
                                        'vorname'       => $zeile['vorname'] ?: null,
                                        'nachname'      => $zeile['nachname'] ?: '(unbekannt)',
                                        'strasse'       => $zeile['strasse'] ?: null,
                                        'plz'           => $zeile['plz'],
                                        'ort'           => $zeile['ort'] ?: null,
                                        'bemerkung'     => $zeile['bemerkung'] ?: null,
                                    ]);
                                    $stats['spender_neu']++;
                                }

                                // Determine year for Bescheinigungsnummer
                                $datum = $zeile['spendendatum']
                                    ? (int) substr($zeile['spendendatum'], 0, 4)
                                    : (int) date('Y');

                                $betragInWortenText = $betragInWorten->konvertiere((float) $zeile['betrag']);

                                Spende::create([
                                    'bescheinigungsnummer' => $bescheinigungsNr->generiere($datum),
                                    'spender_id'           => $spender->id,
                                    'spendendatum'         => $zeile['spendendatum'] ?? now()->toDateString(),
                                    'betrag'               => $zeile['betrag'],
                                    'betrag_in_worten'     => $betragInWortenText,
                                    'foerderungszweck_id'  => $defaultZweck->id,
                                    'ankreuzfeld'          => 'unmittelbar',
                                    'ausstellungsdatum'    => $zeile['spendendatum'] ?? now()->toDateString(),
                                    'status'               => SpendeStatus::Gedruckt->value,
                                    'alte_lfd_nr'          => $zeile['alte_lfd_nr'] ?: null,
                                    'bemerkung'            => null, // bemerkung is on Spender
                                ]);

                                $stats['spenden']++;
                            } else {
                                $stats['uebersprungen']++;
                            }
                        });
                    } else {
                        // Dry run
                        $matchResult = $matcher->finde($zeile);
                        $stats['spenden']++;
                        if ($matchResult['empfehlung'] === 'verwenden') {
                            $stats['spender_gematcht']++;
                        } else {
                            $stats['spender_neu']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $stats['fehler']++;
                    $this->warn("\n  Fehler in Zeile (lfd. Nr. {$zeile['alte_lfd_nr']}): " . $e->getMessage());
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Gesamt', 'Spenden', 'Neue Spender', 'Gematchte Spender', 'Übersprungen', 'Fehler'],
            [array_values($stats)],
        );

        if ($isDryRun) {
            $this->warn("DRY RUN – keine Daten wurden gespeichert.");
        } else {
            $this->info("Import abgeschlossen.");
        }

        return self::SUCCESS;
    }
}
