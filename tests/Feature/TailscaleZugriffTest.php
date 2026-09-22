<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TailscaleZugriffTest extends TestCase
{
    use RefreshDatabase;

    private function vonIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_admin_panel_ist_von_aussen_gesperrt(): void
    {
        config(['spendenquittung.tailscale_only' => true]);

        $this->vonIp('8.8.8.8')
            ->get('/admin/login')
            ->assertForbidden();
    }

    public function test_admin_panel_ist_aus_dem_tailscale_netz_erreichbar(): void
    {
        config(['spendenquittung.tailscale_only' => true]);

        $this->vonIp('100.101.102.103')
            ->get('/admin/login')
            ->assertOk();
    }

    public function test_abgeschaltete_sperre_laesst_alles_durch(): void
    {
        config(['spendenquittung.tailscale_only' => false]);

        $this->vonIp('8.8.8.8')
            ->get('/admin/login')
            ->assertOk();
    }
}
