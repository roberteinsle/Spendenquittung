<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'login_pin', 'ist_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Every account in this application is a staff account — users are created
     * by an administrator, there is no self-registration. Without this method
     * Filament denies access to everyone outside a local environment.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'login_pin' => 'hashed',
            'ist_admin' => 'boolean',
        ];
    }

    /**
     * Administrators may manage settings and user accounts. Everyone else only
     * works with donors and receipts.
     */
    public function istAdmin(): bool
    {
        return (bool) $this->ist_admin;
    }

    /**
     * Users without a PIN sign in by clicking their name.
     */
    public function brauchtPin(): bool
    {
        return filled($this->login_pin);
    }

    public function pinStimmt(string $pin): bool
    {
        return $this->brauchtPin() && Hash::check($pin, $this->login_pin);
    }
}
