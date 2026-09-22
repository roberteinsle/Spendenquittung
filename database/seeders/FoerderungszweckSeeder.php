<?php

namespace Database\Seeders;

use App\Models\Foerderungszweck;
use Illuminate\Database\Seeder;

class FoerderungszweckSeeder extends Seeder
{
    public function run(): void
    {
        $zwecke = [
            [
                'name'       => 'Allgemein',
                'text'       => 'der Förderung bedürftiger Kinder und Jugendlicher',
                'aktiv'      => true,
                'sortierung' => 10,
            ],
            [
                'name'       => 'KiHi Kapstadt',
                'text'       => 'der Förderung des Kinderhilfswerks Kapstadt',
                'aktiv'      => true,
                'sortierung' => 20,
            ],
            [
                'name'       => 'Kleiderkammer',
                'text'       => 'der Förderung der Kleiderkammer für Bedürftige',
                'aktiv'      => true,
                'sortierung' => 30,
            ],
        ];

        foreach ($zwecke as $data) {
            Foerderungszweck::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
