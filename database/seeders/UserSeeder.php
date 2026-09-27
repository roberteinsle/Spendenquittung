<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Creates a single bootstrap account, and only while there is no user at
     * all. The seeders run on every container start, so re-creating accounts
     * here would silently resurrect any user an administrator deleted — and
     * these accounts sign in without a secret.
     *
     * Real staff accounts are created in the panel under "Benutzer".
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        User::create([
            'name'  => 'Administrator',
            'email' => 'admin@example.com',
            // Sign-in is by name, this column is only filled because it is not
            // nullable. Setting a PIN happens in the panel.
            'password'  => Str::random(64),
            'login_pin' => null,
            'ist_admin' => true,
        ]);
    }
}
