<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TailscaleOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('spendenquittung.tailscale_only', true)) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($this->isTailscaleIp($ip) || $this->isLocalhost($ip)) {
            return $next($request);
        }

        abort(403, 'Externer Zugriff nicht möglich.');
    }

    private function isTailscaleIp(string $ip): bool
    {
        // Tailscale CGNAT range: 100.64.0.0 – 100.127.255.255 (/10)
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        return $long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255');
    }

    private function isLocalhost(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true);
    }
}
