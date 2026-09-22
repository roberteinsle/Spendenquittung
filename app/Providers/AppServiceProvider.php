<?php

namespace App\Providers;

use App\Models\Spende;
use App\Models\Spender;
use App\Observers\SpendeObserver;
use App\Observers\SpenderObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Spender::observe(SpenderObserver::class);
        Spende::observe(SpendeObserver::class);
    }
}
