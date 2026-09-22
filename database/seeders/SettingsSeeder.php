<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Stiftungsdaten
            'stiftung_name'              => 'Dietrich F. Liedelt Stiftung',
            'stiftung_strasse'           => 'Möhlendannen 46',
            'stiftung_plz_ort'           => '22391 Hamburg',
            'stiftung_telefon'           => '',
            'stiftung_mobil'             => '',
            'stiftung_email'             => 'kontakt@dfliedelt-stiftung.de',
            'stiftung_web'               => 'www.dfliedelt-stiftung.de',
            // Spendenkonto
            'spendenkonto_kontoempfaenger' => 'Dietrich F. Liedelt Stiftung',
            'spendenkonto_iban'          => 'DE29 2003 0700 1050 6965 57',
            'spendenkonto_bic'           => 'MEFIDEMM200',
            'spendenkonto_bank'          => 'Merck Finck',
            // Rechtliche Form (placeholder | oeffentlich_rechtlich | privatrechtlich)
            'rechtliche_form'            => 'placeholder',
            // Freistellungsbescheid (nur für privatrechtliche Stiftung)
            'stiftung_finanzamt'         => '',
            'stiftung_steuernummer'      => '',
            'stiftung_freistellung_datum' => '',
            'stiftung_veranlagungszeitraum' => '',
            // Asset-Pfade (werden nach Upload gesetzt)
            'unterschrift_pfad'          => '',
            'logo_pfad'                  => '',
            'herzfigur_pfad'             => '',
            // Unterzeichner-Zeile auf PDF
            'unterzeichner_name'         => 'Jasmin Einsle',
            'unterzeichner_titel'        => 'Vorstand',
            'ausstellungsort'            => 'Hamburg',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string'],
            );
        }
    }
}
