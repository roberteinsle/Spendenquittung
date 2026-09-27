<?php

namespace App\Models;

use App\Enums\ProtokollAktion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Protokolleintrag extends Model
{
    protected $table = 'protokolle';

    protected $fillable = [
        'benutzer_id',
        'benutzer_name',
        'aktion',
        'betrifft_type',
        'betrifft_id',
        'bezeichnung',
        'beschreibung',
        'aenderungen',
    ];

    protected $casts = [
        'aktion' => ProtokollAktion::class,
        'aenderungen' => 'array',
    ];

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id');
    }

    public function betrifft(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * "Spender" oder "Bescheinigung" – für Anzeige und Filter.
     */
    public function getArtAttribute(): string
    {
        return match ($this->betrifft_type) {
            Spende::class => 'Bescheinigung',
            Spender::class => 'Spender',
            default => '—',
        };
    }
}
