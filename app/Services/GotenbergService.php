<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GotenbergService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('spendenquittung.gotenberg_url', 'http://gotenberg:3000'), '/');
    }

    /**
     * Convert HTML string to PDF bytes via Gotenberg Chromium.
     *
     * @param string $html Full HTML document
     * @return string Raw PDF bytes
     */
    public function htmlZuPdf(string $html): string
    {
        try {
            $response = Http::timeout(30)
                ->attach('index.html', $html, 'index.html')
                ->post("{$this->baseUrl}/forms/chromium/convert/html", [
                    'paperWidth'  => '21cm',
                    'paperHeight' => '29.7cm',
                    'marginTop'   => '0cm',
                    'marginBottom' => '0cm',
                    'marginLeft'  => '0cm',
                    'marginRight' => '0cm',
                    'printBackground' => 'true',
                    'preferCssPageSize' => 'false',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Gotenberg returned HTTP {$response->status()}: {$response->body()}"
                );
            }

            return $response->body();
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Gotenberg nicht erreichbar ({$this->baseUrl}). " .
                "Ist der Gotenberg-Container gestartet? Fehler: " . $e->getMessage()
            );
        }
    }

    /**
     * Check if Gotenberg is available.
     */
    public function istVerfuegbar(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
