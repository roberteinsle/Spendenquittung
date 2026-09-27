<?php

namespace Tests\Feature;

use App\Filament\Auth\BenutzerAuswahl;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnmeldungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spendenquittung.tailscale_only' => false]);
    }

    private function ohnePin(string $name = 'Andrea Beispiel'): User
    {
        return User::factory()->create(['name' => $name, 'login_pin' => null]);
    }

    private function mitPin(string $pin = '135790', string $name = 'Chris Beispiel'): User
    {
        return User::factory()->create(['name' => $name, 'login_pin' => $pin]);
    }

    public function test_anmeldeseite_listet_die_benutzer(): void
    {
        $this->ohnePin('Andrea Beispiel');
        $this->mitPin(name: 'Chris Beispiel');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Andrea Beispiel')
            ->assertSee('Chris Beispiel');
    }

    public function test_anmeldeseite_traegt_den_namen_der_app_nicht_laravel(): void
    {
        $this->ohnePin();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Spendenquittung')
            ->assertDontSee('Laravel');
    }

    public function test_ohne_benutzer_erklaert_die_seite_was_zu_tun_ist(): void
    {
        $this->assertSame(0, User::count());

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Es ist noch kein Benutzer angelegt.')
            ->assertSee('db:seed --force', escape: false);
    }

    public function test_klick_auf_namen_meldet_ohne_pin_an(): void
    {
        $benutzer = $this->ohnePin();

        Livewire::test(BenutzerAuswahl::class)
            ->call('waehle', $benutzer->id);

        $this->assertAuthenticatedAs($benutzer);
    }

    public function test_benutzer_mit_pin_wird_nicht_sofort_angemeldet(): void
    {
        $benutzer = $this->mitPin();

        Livewire::test(BenutzerAuswahl::class)
            ->call('waehle', $benutzer->id)
            ->assertSet('benutzerId', $benutzer->id);

        $this->assertGuest();
    }

    public function test_richtige_pin_meldet_an(): void
    {
        $benutzer = $this->mitPin('135790');

        Livewire::test(BenutzerAuswahl::class)
            ->call('waehle', $benutzer->id)
            ->set('pin', '135790')
            ->call('anmelden')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($benutzer);
    }

    public function test_falsche_pin_meldet_nicht_an(): void
    {
        $benutzer = $this->mitPin('135790');

        Livewire::test(BenutzerAuswahl::class)
            ->call('waehle', $benutzer->id)
            ->set('pin', '9999')
            ->call('anmelden')
            ->assertHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_versuche_werden_gedrosselt(): void
    {
        $benutzer = $this->mitPin('135790');

        $seite = Livewire::test(BenutzerAuswahl::class)
            ->call('waehle', $benutzer->id);

        for ($i = 0; $i < 5; $i++) {
            $seite->set('pin', '0000')->call('anmelden')->assertHasErrors('pin');
        }

        // Even the correct PIN must be refused once the limit is reached.
        $seite->set('pin', '135790')->call('anmelden')->assertHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_kann_nicht_ueber_den_namen_umgangen_werden(): void
    {
        $benutzer = $this->mitPin('135790');

        // Calling the sign-in step without ever passing the PIN check.
        Livewire::test(BenutzerAuswahl::class)
            ->set('pin', '')
            ->call('anmelden');

        $this->assertGuest();
    }

    public function test_seeder_legt_nur_an_wenn_es_keinen_benutzer_gibt(): void
    {
        $this->seed(UserSeeder::class);
        $this->assertSame(1, User::count());

        // An administrator deleted the bootstrap account and created their own.
        User::query()->delete();
        $eigener = $this->ohnePin('Eigenes Konto');

        // The seeders run again on every container start.
        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(User::first()->is($eigener));
    }

    public function test_angemeldeter_benutzer_wird_ins_panel_geleitet(): void
    {
        $benutzer = $this->ohnePin();

        $this->actingAs($benutzer)
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }
}
