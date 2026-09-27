<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Spendes\SpendeResource;
use App\Models\Spende;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LetzteBescheinigungen extends TableWidget
{
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Letzte Bescheinigungen')
            ->query(
                Spende::query()
                    ->with(['spender'])
                    // created_at allein reicht nicht: der Import legt viele
                    // Spenden in derselben Sekunde an, die Reihenfolge waere
                    // dann zufaellig.
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('bescheinigungsnummer')
                    ->label('Nr.')
                    ->fontFamily('mono'),

                TextColumn::make('spender.nachname')
                    ->label('Spender')
                    ->formatStateUsing(fn ($record) => $record->spender?->vollname)
                    ->wrap(),

                TextColumn::make('betrag')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de')
                    ->alignEnd(),

                TextColumn::make('spendendatum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->toggleable(),
            ])
            ->recordUrl(fn (Spende $record): string => SpendeResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Noch keine Bescheinigung erfasst')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
