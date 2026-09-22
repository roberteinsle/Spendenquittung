<?php

namespace App\Filament\Resources\Spendes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersandprotokolleRelationManager extends RelationManager
{
    protected static string $relationship = 'versandprotokolle';

    protected static ?string $title = 'Versandprotokoll';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zeitpunkt')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('kanal')
                    ->label('Kanal')
                    ->badge(),

                TextColumn::make('ergebnis')
                    ->label('Ergebnis')
                    ->badge(),

                TextColumn::make('empfaenger')
                    ->label('Empfänger')
                    ->placeholder('—'),

                TextColumn::make('ausgefuehrtVon.name')
                    ->label('Benutzer')
                    ->placeholder('—'),

                TextColumn::make('nachricht')
                    ->label('Nachricht')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('zeitpunkt', 'desc')
            ->emptyStateHeading('Noch kein Versand protokolliert')
            ->emptyStateDescription('Sobald das PDF geöffnet oder versendet wird, erscheint hier ein Eintrag.');
    }
}
