<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SpendeStatus: string implements HasLabel, HasColor
{
    case Erfasst   = 'erfasst';
    case Erstellt  = 'erstellt';
    case Gedruckt  = 'gedruckt';
    case Versendet = 'versendet';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Erfasst   => 'Erfasst',
            self::Erstellt  => 'PDF erstellt',
            self::Gedruckt  => 'Gedruckt',
            self::Versendet => 'Versendet',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Erfasst   => 'gray',
            self::Erstellt  => 'warning',
            self::Gedruckt  => 'success',
            self::Versendet => 'info',
        };
    }
}
