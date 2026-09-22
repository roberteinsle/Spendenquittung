<?php

namespace App\Models;

use App\Enums\VersandKanal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Versandprotokoll extends Model
{
    protected $table = 'versandprotokolle';

    protected $fillable = [
        'spende_id',
        'kanal',
        'zeitpunkt',
        'empfaenger',
        'ergebnis',
        'nachricht',
        'ausgefuehrt_von',
    ];

    protected $casts = [
        'kanal'     => VersandKanal::class,
        'zeitpunkt' => 'datetime',
    ];

    public function spende(): BelongsTo
    {
        return $this->belongsTo(Spende::class);
    }

    public function ausgefuehrtVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ausgefuehrt_von');
    }
}
