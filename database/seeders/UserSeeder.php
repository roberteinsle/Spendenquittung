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
                'name'     => 'Admin',
                'email'    => 'admin@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name'     => 'User 1',
                'email'    => 'user1@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name'     => 'User 2',
                'email'    => 'user2@example.com',
                'password' => Hash::make('password'),
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
