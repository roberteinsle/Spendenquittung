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

    /**
     * Position in the workflow. Used to prevent a status from moving backwards,
     * e.g. when a PDF is regenerated for a receipt that was already sent.
     */
    public function stufe(): int
    {
        return match($this) {
            self::Erfasst   => 0,
            self::Erstellt  => 1,
            self::Gedruckt  => 2,
            self::Versendet => 3,
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
