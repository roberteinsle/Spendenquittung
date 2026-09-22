<?php

namespace App\Observers;

use App\Models\Spender;
use App\Services\SpendernummerService;

class SpenderObserver
{
    public function creating(Spender $spender): void
    {
        if (empty($spender->spendernummer)) {
            $spender->spendernummer = app(SpendernummerService::class)->generiere();
        }
    }
}
