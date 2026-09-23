<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ImportAktion: string implements HasColor, HasLabel
{
    case Verwenden     = 'verwenden';
    case NeuAnlegen    = 'neu_anlegen';
    case Ueberspringen = 'ueberspringen';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Verwenden     => 'Vorhandenen Spender verwenden',
            self::NeuAnlegen    => 'Neuen Spender anlegen',
            self::Ueberspringen => 'Zeile überspringen',
        };
    }

    /**
     * Coerce a value that may already be an enum instance. The wizard writes
     * plain strings into the repeater state, but once Filament's Select has
     * hydrated a row, the same key holds an ImportAktion instead.
     */
    public static function ausWert(mixed $wert): ?self
    {
        if ($wert instanceof self) {
            return $wert;
        }

        return is_string($wert) ? self::tryFrom($wert) : null;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Verwenden     => 'success',
            self::NeuAnlegen    => 'info',
            self::Ueberspringen => 'gray',
        };
    }
}
