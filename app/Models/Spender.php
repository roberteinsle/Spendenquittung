<?php

namespace App\Models;

use App\Enums\Anrede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spender extends Model
{
    use SoftDeletes;

    protected $table = 'spender';

    protected $fillable = [
        'spendernummer',
        'anrede',
        'firma',
        'vorname',
        'nachname',
        'strasse',
        'plz',
        'ort',
        'email',
        'duzen',
        'bemerkung',
        'aktiv',
    ];

    protected $casts = [
        'anrede' => Anrede::class,
        'duzen'  => 'boolean',
        'aktiv'  => 'boolean',
    ];

    public function spenden(): HasMany
    {
        return $this->hasMany(Spende::class);
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', true);
    }

    /**
     * Full name for display, considering Anrede and Firma.
     */
    public function getVollnameAttribute(): string
    {
        if ($this->firma && in_array($this->anrede?->value, ['Firma', null], true)) {
            return $this->firma;
        }

        $parts = array_filter([
            $this->vorname,
            $this->nachname,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Formatted address block for PDF, multi-line.
     */
    public function getAdressblockAttribute(): string
    {
        $lines = [];

        // Salutation line
        if ($this->anrede) {
            $anrede = $this->anrede->value;
            if ($this->firma) {
                $lines[] = $this->firma;
                if ($this->vorname || $this->nachname) {
                    $lines[] = trim("{$anrede} " . trim("{$this->vorname} {$this->nachname}"));
                }
            } else {
                $lines[] = trim("{$anrede} " . trim("{$this->vorname} {$this->nachname}"));
            }
        } else {
            if ($this->firma) {
                $lines[] = $this->firma;
            }
            $lines[] = trim("{$this->vorname} {$this->nachname}");
        }

        if ($this->strasse) {
            $lines[] = $this->strasse;
        }

        if ($this->plz || $this->ort) {
            $lines[] = trim("{$this->plz} {$this->ort}");
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Normalised key for donor matching: lowercase nachname + normalised plz.
     */
    public function getNormalisierterMatchkeyAttribute(): string
    {
        return static::normalisiereMatchkey(
            $this->firma ?: $this->nachname,
            $this->plz ?? ''
        );
    }

    public static function normalisiereMatchkey(string $name, string $plz): string
    {
        $name = mb_strtolower($name);
        $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
        $name = preg_replace('/\s+/', '', $name);
        $name = preg_replace('/[^a-z0-9]/', '', $name);

        $plz = preg_replace('/\D/', '', $plz);

        return "{$name}_{$plz}";
    }
}
