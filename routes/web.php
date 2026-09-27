<?php

use App\Http\Controllers\BescheinigungPdfController;
use Illuminate\Support\Facades\Route;

// The application is the Filament panel; there is no public front page.
Route::redirect('/', '/admin');

Route::middleware('auth')
    ->get('/bescheinigungen/{spende}/pdf', BescheinigungPdfController::class)
    ->name('bescheinigung.pdf');
