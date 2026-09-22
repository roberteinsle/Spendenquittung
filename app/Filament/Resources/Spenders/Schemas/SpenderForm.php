<?php

namespace App\Filament\Resources\Spenders\Schemas;

use App\Enums\Anrede;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SpenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('anrede')
                            ->label('Anrede')
                            ->options(Anrede::class)
                            ->native(false),

                        TextInput::make('spendernummer')
                            ->label('Spendernummer')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('wird automatisch vergeben'),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('vorname')
                            ->label('Vorname'),

                        TextInput::make('nachname')
                            ->label('Nachname')
                            ->required(),
                    ]),

                TextInput::make('firma')
                    ->label('Firma / Organisation')
                    ->columnSpanFull(),

                TextInput::make('strasse')
                    ->label('Straße')
                    ->columnSpanFull(),

                Grid::make(3)
                    ->schema([
                        TextInput::make('plz')
                            ->label('PLZ')
                            ->maxLength(10),

                        TextInput::make('ort')
                            ->label('Ort')
                            ->columnSpan(2),
                    ]),

                TextInput::make('email')
                    ->label('E-Mail')
                    ->email(),

                Grid::make(2)
                    ->schema([
                        Toggle::make('duzen')
                            ->label('Per Du ansprechen'),

                        Toggle::make('aktiv')
                            ->label('Aktiv')
                            ->default(true),
                    ]),

                Textarea::make('bemerkung')
                    ->label('Bemerkung')
                    ->columnSpanFull()
                    ->rows(3),
            ]);
    }
}
