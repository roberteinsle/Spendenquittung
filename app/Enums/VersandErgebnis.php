<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VersandErgebnis: string implements HasLabel, HasColor
{
    case Erfolg = 'success';
    case Fehler = 'fehler';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Erfolg => 'Erfolgreich',
            self::Fehler => 'Fehler',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Erfolg => 'success',
            self::Fehler => 'danger',
        };
    }
}
