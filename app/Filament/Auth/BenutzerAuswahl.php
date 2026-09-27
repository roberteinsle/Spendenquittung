<?php

namespace App\Filament\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Sign-in by picking a name. The panel is only reachable inside the private
 * network, so staff accounts need no secret; accounts that carry a login PIN
 * are challenged for it.
 */
class BenutzerAuswahl extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.auth.benutzer-auswahl';

    #[Locked]
    public ?int $benutzerId = null;

    public string $pin = '';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function getBenutzer(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    public function getGewaehlterBenutzer(): ?User
    {
        return $this->benutzerId ? User::find($this->benutzerId) : null;
    }

    public function waehle(int $benutzerId): mixed
    {
        $benutzer = User::find($benutzerId);

        if (! $benutzer) {
            return null;
        }

        if ($benutzer->brauchtPin()) {
            $this->benutzerId = $benutzer->id;
            $this->pin        = '';

            return null;
        }

        return $this->melde($benutzer);
    }

    public function anmelden(): mixed
    {
        $benutzer = $this->getGewaehlterBenutzer();

        if (! $benutzer) {
            return $this->zurueck();
        }

        // A four digit PIN is guessed in minutes without this.
        try {
            $this->rateLimit(5, method: 'anmelden');
        } catch (TooManyRequestsException $e) {
            throw ValidationException::withMessages([
                'pin' => "Zu viele Fehlversuche. Bitte {$e->secondsUntilAvailable} Sekunden warten.",
            ]);
        }

        if (! $benutzer->pinStimmt($this->pin)) {
            $this->pin = '';

            throw ValidationException::withMessages([
                'pin' => 'Die PIN stimmt nicht.',
            ]);
        }

        return $this->melde($benutzer);
    }

    public function zurueck(): null
    {
        $this->benutzerId = null;
        $this->pin        = '';
        $this->resetErrorBag();

        return null;
    }

    private function melde(User $benutzer): LoginResponse
    {
        Filament::auth()->login($benutzer, remember: true);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function getHeading(): string
    {
        return $this->getGewaehlterBenutzer()
            ? 'PIN eingeben'
            : 'Anmelden';
    }

    public function getSubheading(): ?string
    {
        return $this->getGewaehlterBenutzer()
            ? null
            : 'Bitte den eigenen Namen wählen.';
    }

    public function getTitle(): string
    {
        return 'Anmelden';
    }
}
