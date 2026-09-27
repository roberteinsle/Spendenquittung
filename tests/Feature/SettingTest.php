<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_string_wird_gespeichert_und_gelesen(): void
    {
        Setting::set('stiftung_name', 'Musterstiftung');

        $this->assertSame('Musterstiftung', Setting::get('stiftung_name'));
    }

    public function test_unbekannter_schluessel_liefert_default(): void
    {
        $this->assertSame('fallback', Setting::get('gibt_es_nicht', 'fallback'));
    }

    public function test_boolean_und_json_behalten_ihren_typ(): void
    {
        Setting::set('flag', true);
        Setting::set('liste', ['a', 'b']);

        $this->assertTrue(Setting::get('flag'));
        $this->assertSame(['a', 'b'], Setting::get('liste'));
    }

    public function test_aenderung_invalidiert_den_cache(): void
    {
        Setting::set('mail_betreff', 'alt');
        $this->assertSame('alt', Setting::get('mail_betreff'));

        Setting::set('mail_betreff', 'neu');
        $this->assertSame('neu', Setting::get('mail_betreff'));
    }
}
