<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Spenders\SpenderResource;
use App\Models\Spender;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LetzteSpender extends TableWidget
{
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Letzte Spender')
            ->query(
                Spender::query()
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('spendernummer')
                    ->label('Nr.')
                    ->fontFamily('mono'),

                TextColumn::make('nachname')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => $record->vollname)
                    ->wrap(),

                TextColumn::make('ort')
                    ->label('Ort')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->date('d.m.Y')
                    ->toggleable(),
            ])
            ->recordUrl(fn (Spender $record): string => SpenderResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Noch kein Spender angelegt')
            ->emptyStateIcon('heroicon-o-users');
    }
}
