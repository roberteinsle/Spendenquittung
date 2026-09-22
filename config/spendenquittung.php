<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tailscale Access Restriction
    |--------------------------------------------------------------------------
    | When enabled, only requests from Tailscale CGNAT range (100.64.0.0/10)
    | and localhost are allowed. Set to false for local development.
    */
    'tailscale_only' => env('TAILSCALE_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Gotenberg PDF Service
    |--------------------------------------------------------------------------
    */
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

    /*
    |--------------------------------------------------------------------------
    | Storage Paths (relative to storage/app/public/)
    |--------------------------------------------------------------------------
    */
    'pdf_storage_path'        => 'pdfs',
    'signature_storage_path'  => 'unterschriften',
    'logo_storage_path'       => 'logos',

    /*
    |--------------------------------------------------------------------------
    | Number Generation
    |--------------------------------------------------------------------------
    */
    'bescheinigungsnummer_max_attempts' => 100,
    'spendernummer_max_attempts'        => 100,

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    */
    'import_chunk_size'       => 200,
    'matching_fuzzy_threshold' => 2, // Levenshtein max distance for donor matching
];
