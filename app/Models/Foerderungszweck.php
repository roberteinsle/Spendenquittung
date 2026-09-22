<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Foerderungszweck extends Model
{
    use SoftDeletes;

    protected $table = 'foerderungszwecke';

    protected $fillable = [
        'name',
        'text',
        'aktiv',
        'sortierung',
    ];

    protected $casts = [
        'aktiv'      => 'boolean',
        'sortierung' => 'integer',
    ];

    public function spenden(): HasMany
    {
        return $this->hasMany(Spende::class);
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', true)->orderBy('sortierung')->orderBy('name');
    }
}
