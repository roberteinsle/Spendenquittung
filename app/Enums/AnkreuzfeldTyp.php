<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AnkreuzfeldTyp: string implements HasLabel
{
    case Vermoegenstock = 'vermoegenstock';
    case Unmittelbar    = 'unmittelbar';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Vermoegenstock => 'wurde in den Vermögensstock eingebracht',
            self::Unmittelbar    => 'wird von uns unmittelbar für den angegebenen Zweck verwendet',
        };
    }

    public function getLabelShort(): string
    {
        return match($this) {
            self::Vermoegenstock => 'Vermögensstock',
            self::Unmittelbar    => 'Unmittelbar für Zweck',
        };
    }
}
