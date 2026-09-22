<?php

namespace App\Services;

use App\Enums\SpendeStatus;
use App\Models\Setting;
use App\Models\Spende;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PdfGeneratorService
{
    public function __construct(
        private GotenbergService $gotenberg,
    ) {}

    /**
     * Generate a PDF for a single Spende, store it, and update the record.
     *
     * @return string Storage path relative to public disk
     */
    public function generiere(Spende $spende): string
    {
        $spende->load(['spender', 'foerderungszweck', 'erstelltVon']);

        $settings = $this->ladeSettings();
        $html     = $this->rendereTemplate($spende, $settings);
        $pdfBytes = $this->gotenberg->htmlZuPdf($html);

        $pfad     = config('spendenquittung.pdf_storage_path') . '/' . $spende->bescheinigungsnummer . '.pdf';

        Storage::disk('public')->put($pfad, $pdfBytes);

        $spende->update([
            'pdf_pfad' => $pfad,
            'status'   => SpendeStatus::Erstellt,
        ]);

        return $pfad;
    }

    /**
     * Generate PDFs for a collection of Spenden.
     *
     * @param \Illuminate\Database\Eloquent\Collection<Spende> $spenden
     * @return array<string> Storage paths
     */
    public function generiereStapel(iterable $spenden): array
    {
        $pfade = [];
        foreach ($spenden as $spende) {
            $pfade[] = $this->generiere($spende);
        }
        return $pfade;
    }

    private function ladeSettings(): array
    {
        $keys = [
            'stiftung_name', 'stiftung_strasse', 'stiftung_plz_ort',
            'stiftung_telefon', 'stiftung_mobil', 'stiftung_email', 'stiftung_web',
            'spendenkonto_kontoempfaenger', 'spendenkonto_iban', 'spendenkonto_bic', 'spendenkonto_bank',
            'rechtliche_form',
            'stiftung_finanzamt', 'stiftung_steuernummer', 'stiftung_freistellung_datum', 'stiftung_veranlagungszeitraum',
            'unterzeichner_name', 'unterzeichner_titel', 'ausstellungsort',
            'unterschrift_pfad', 'logo_pfad', 'herzfigur_pfad',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::get($key, '');
        }

        // Convert asset paths to base64 data URIs for embedding in HTML
        foreach (['unterschrift_pfad', 'logo_pfad', 'herzfigur_pfad'] as $assetKey) {
            $pfad = $settings[$assetKey];
            if ($pfad && Storage::disk('public')->exists($pfad)) {
                $bytes    = Storage::disk('public')->get($pfad);
                $mime     = Storage::disk('public')->mimeType($pfad);
                $settings[$assetKey . '_data_uri'] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            } else {
                $settings[$assetKey . '_data_uri'] = null;
            }
        }

        return $settings;
    }

    private function rendereTemplate(Spende $spende, array $settings): string
    {
        return view('pdf.zuwendungsbestaetigung', [
            'spende'   => $spende,
            'spender'  => $spende->spender,
            'zweck'    => $spende->foerderungszweck,
            'settings' => $settings,
        ])->render();
    }
}
