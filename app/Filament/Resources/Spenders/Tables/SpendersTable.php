<?php

namespace App\Filament\Resources\Spenders\Tables;

use App\Enums\Anrede;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SpendersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('spendernummer')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('anrede')
                    ->label('Anrede')
                    ->badge()
                    ->sortable(),

                TextColumn::make('nachname')
                    ->label('Nachname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vorname')
                    ->label('Vorname')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('firma')
                    ->label('Firma')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('plz')
                    ->label('PLZ')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('ort')
                    ->label('Ort')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('spenden_count')
                    ->label('Spenden')
                    ->counts('spenden')
                    ->sortable()
                    ->alignRight(),

                IconColumn::make('aktiv')
                    ->label('Aktiv')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('nachname')
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('aktiv')->label('Aktiv'),
                SelectFilter::make('anrede')
                    ->label('Anrede')
                    ->options(Anrede::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
