<?php

namespace App\Models;

use App\Enums\AnkreuzfeldTyp;
use App\Enums\SpendeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Spende extends Model
{
    use SoftDeletes;

    protected $table = 'spenden';

    protected $fillable = [
        'bescheinigungsnummer',
        'spender_id',
        'spendendatum',
        'betrag',
        'betrag_in_worten',
        'foerderungszweck_id',
        'anlass',
        'ankreuzfeld',
        'ausstellungsdatum',
        'status',
        'alte_lfd_nr',
        'pdf_pfad',
        'erstellt_von',
    ];

    protected $casts = [
        'spendendatum'    => 'date',
        'ausstellungsdatum' => 'date',
        'betrag'          => 'decimal:2',
        'status'          => SpendeStatus::class,
        'ankreuzfeld'     => AnkreuzfeldTyp::class,
    ];

    public function spender(): BelongsTo
    {
        return $this->belongsTo(Spender::class);
    }

    public function foerderungszweck(): BelongsTo
    {
        return $this->belongsTo(Foerderungszweck::class);
    }

    public function erstelltVon(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'erstellt_von');
    }

    public function versandprotokolle(): HasMany
    {
        return $this->hasMany(Versandprotokoll::class);
    }

    /**
     * Formatted Euro amount: "70,70 €"
     */
    public function getBetragFormatiertAttribute(): string
    {
        return number_format((float) $this->betrag, 2, ',', '.') . ' €';
    }

    /**
     * True when a generated PDF is actually present on the storage disk.
     */
    public function pdfVorhanden(): bool
    {
        return $this->pdf_pfad !== null
            && Storage::disk(config('spendenquittung.pdf_disk'))->exists($this->pdf_pfad);
    }

    public function getPdfDateinameAttribute(): string
    {
        return "Zuwendungsbestaetigung-{$this->bescheinigungsnummer}.pdf";
    }
}
