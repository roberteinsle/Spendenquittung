<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VersandKanal: string implements HasLabel
{
    case Druck = 'druck';
    case Email = 'email';
    case Post  = 'post';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Druck => 'Druck',
            self::Email => 'E-Mail',
            self::Post  => 'Post',
        };
    }
}
