<?php

namespace App\Http\Controllers;

use App\Enums\VersandKanal;
use App\Models\Spende;
use App\Services\VersandprotokollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BescheinigungPdfController extends Controller
{
    /**
     * Serve a generated receipt to an authenticated user.
     *
     * The PDFs live on a private disk because they contain personal data, so
     * this route is the only way to get at them. Every access is recorded as a
     * "Druck" in the Versandprotokoll.
     */
    public function __invoke(
        Request $request,
        Spende $spende,
        VersandprotokollService $versandprotokoll,
    ): StreamedResponse {
        abort_unless($spende->pdfVorhanden(), 404);

        $versandprotokoll->protokolliere(
            spende: $spende,
            kanal: VersandKanal::Druck,
            nachricht: 'PDF im Browser geöffnet',
        );

        return Storage::disk(config('spendenquittung.pdf_disk'))->response(
            $spende->pdf_pfad,
            $spende->pdf_dateiname,
            ['Content-Type' => 'application/pdf'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }
}
