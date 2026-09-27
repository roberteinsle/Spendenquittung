<?php

namespace Tests\Feature;

use App\Enums\Anrede;
use App\Models\Spender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BriefanredeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function faelle(): array
    {
        return [
            'Herr formell' => [
                ['anrede' => Anrede::Herrn, 'vorname' => 'Max', 'nachname' => 'Mustermann'],
                'Sehr geehrter Herr Mustermann',
            ],
            'Frau formell' => [
                ['anrede' => Anrede::Frau, 'vorname' => 'Erika', 'nachname' => 'Musterfrau'],
                'Sehr geehrte Frau Musterfrau',
            ],
            'Eheleute formell' => [
                ['anrede' => Anrede::Eheleute, 'nachname' => 'Mustermann'],
                'Sehr geehrte Eheleute Mustermann',
            ],
            'Firma formell' => [
                ['anrede' => Anrede::Firma, 'firma' => 'Muster GmbH', 'nachname' => 'Muster GmbH'],
                'Sehr geehrte Damen und Herren',
            ],
            'ohne Anrede vermeidet Herr/Frau' => [
                ['anrede' => null, 'vorname' => 'Kim', 'nachname' => 'Muster'],
                'Guten Tag Kim Muster',
            ],
            'Herr geduzt' => [
                ['anrede' => Anrede::Herrn, 'vorname' => 'Max', 'nachname' => 'Mustermann', 'duzen' => true],
                'Lieber Max',
            ],
            'Frau geduzt' => [
                ['anrede' => Anrede::Frau, 'vorname' => 'Erika', 'nachname' => 'Musterfrau', 'duzen' => true],
                'Liebe Erika',
            ],
            'geduzt ohne Vornamen' => [
                ['anrede' => Anrede::Frau, 'nachname' => 'Musterfrau', 'duzen' => true],
                'Hallo',
            ],
            'ohne Anrede geduzt' => [
                ['anrede' => null, 'vorname' => 'Kim', 'nachname' => 'Muster', 'duzen' => true],
                'Hallo Kim',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attribute
     */
    #[DataProvider('faelle')]
    public function test_briefanrede(array $attribute, string $erwartet): void
    {
        $spender = new Spender(array_merge(['spendernummer' => '80001'], $attribute));

        $this->assertSame($erwartet, $spender->briefanrede);
    }
}
