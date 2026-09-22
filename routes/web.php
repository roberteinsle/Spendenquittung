<?php

use App\Http\Controllers\BescheinigungPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->get('/bescheinigungen/{spende}/pdf', BescheinigungPdfController::class)
    ->name('bescheinigung.pdf');
