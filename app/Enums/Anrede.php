<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Anrede: string implements HasLabel
{
    case Herrn    = 'Herrn';
    case Frau     = 'Frau';
    case Firma    = 'Firma';
    case Eheleute = 'Eheleute';

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
