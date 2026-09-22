<?php

namespace App\Filament\Resources\Foerderungszwecks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FoerderungszweckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name (kurz)')
                    ->required()
                    ->helperText('Kurzname für Dropdown, z. B. "KiHi Kapstadt"'),

                Textarea::make('text')
                    ->label('Juristischer Volltext')
                    ->required()
                    ->columnSpanFull()
                    ->rows(3)
                    ->helperText('Wird auf der Bescheinigung gedruckt: "der Förderung ..."'),

                \Filament\Forms\Components\Grid::make(2)
                    ->schema([
                        Toggle::make('aktiv')
                            ->label('Aktiv')
                            ->default(true),

                        TextInput::make('sortierung')
                            ->label('Reihenfolge')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
