<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Benutzer')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->helperText('So erscheint der Name auf der Anmeldeseite.')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),

                Section::make('Rechte')
                    ->schema([
                        Toggle::make('ist_admin')
                            ->label('Darf Einstellungen und Benutzer verwalten')
                            // Sonst könnte sich der letzte Administrator selbst
                            // aussperren, ohne Weg zurück über die Oberfläche.
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText(fn (?User $record): string => ($record?->is(auth()->user()) ?? false)
                                ? 'Die eigenen Rechte lassen sich hier nicht entziehen.'
                                : 'Ohne diesen Haken sieht der Benutzer nur Spender, Bescheinigungen und den Import.'
                            ),
                    ]),

                Section::make('Anmeldung')
                    ->description('Ohne PIN meldet sich der Benutzer mit einem Klick auf seinen Namen an. Das Panel ist nur im Tailscale-Netz erreichbar.')
                    ->schema([
                        TextInput::make('login_pin')
                            ->label('PIN')
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(12)
                            ->autocomplete('new-password')
                            // The stored value is a hash; showing it would be
                            // meaningless and saving it back would double-hash.
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText('Leer lassen für Anmeldung per Klick. Beim Bearbeiten leer lassen, um die bestehende PIN zu behalten.'),
                    ]),
            ]);
    }
}
