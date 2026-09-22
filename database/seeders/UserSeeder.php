<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Robert Einsle',
                'email'    => 'robert@einsle.com',
                'password' => Hash::make('change-me-on-first-login'),
            ],
            [
                'name'     => 'Jasmin Einsle',
                'email'    => 'jasmin@dfliedelt-stiftung.de',
                'password' => Hash::make('change-me-on-first-login'),
            ],
            [
                'name'     => 'Andrea Janßen',
                'email'    => 'andrea@dfliedelt-stiftung.de',
                'password' => Hash::make('change-me-on-first-login'),
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                $data,
            );
        }
    }
}
