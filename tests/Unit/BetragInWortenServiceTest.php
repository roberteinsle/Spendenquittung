<?php

namespace Tests\Unit;

use App\Services\BetragInWortenService;
use PHPUnit\Framework\TestCase;

class BetragInWortenServiceTest extends TestCase
{
    private BetragInWortenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BetragInWortenService();
    }

    public function test_runde_betraege(): void
    {
        $this->assertSame('Fünfzig Euro', $this->service->konvertiere(50.00));
        $this->assertSame('Einhundert Euro', $this->service->konvertiere(100.00));
        $this->assertSame('Zweihundert Euro', $this->service->konvertiere(200.00));
    }

    public function test_betrag_mit_cent(): void
    {
        $this->assertSame('Siebzig Euro und siebzig Cent', $this->service->konvertiere(70.70));
        $this->assertSame('Ein Euro und ein Cent', $this->service->konvertiere(1.01));
        $this->assertSame('Zehn Euro und fünfzig Cent', $this->service->konvertiere(10.50));
    }

    public function test_ein_euro(): void
    {
        $this->assertSame('Ein Euro', $this->service->konvertiere(1.00));
    }

    public function test_grosse_betraege(): void
    {
        $this->assertSame('Eintausendzweihundertfünfzig Euro', $this->service->konvertiere(1250.00));
        $this->assertSame('Neuntausendneunhundertneunundneunzig Euro und neunundneunzig Cent',
            $this->service->konvertiere(9999.99));
    }

    public function test_normalisierung_komma_als_dezimal(): void
    {
        $betrag = $this->service->normalisiereEingabe('70,70');
        $this->assertSame('Siebzig Euro und siebzig Cent', $this->service->konvertiere($betrag));
    }

    public function test_normalisierung_punkt_als_tausendertrenner(): void
    {
        $betrag = $this->service->normalisiereEingabe('1.250,00');
        $this->assertEqualsWithDelta(1250.0, $betrag, 0.001);
    }

    public function test_normalisierung_euro_zeichen(): void
    {
        $betrag = $this->service->normalisiereEingabe('150,00 €');
        $this->assertEqualsWithDelta(150.0, $betrag, 0.001);
    }

    public function test_einhundertfuenfzig(): void
    {
        $this->assertSame('Einhundertfünfzig Euro', $this->service->konvertiere(150.00));
    }

    public function test_zwanzig(): void
    {
        $this->assertSame('Zwanzig Euro', $this->service->konvertiere(20.00));
    }

    public function test_einundzwanzig(): void
    {
        $this->assertSame('Einundzwanzig Euro', $this->service->konvertiere(21.00));
    }

    public function test_null_euro(): void
    {
        $this->assertSame('Null Euro', $this->service->konvertiere(0.00));
    }

    public function test_groesster_gueltiger_betrag(): void
    {
        $result = $this->service->konvertiere(99999.99);
        $this->assertStringStartsWith('Neunundneunzigtausend', $result);
    }

    public function test_ungueltige_betraege_werfen_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->konvertiere(-1.00);
    }
}
