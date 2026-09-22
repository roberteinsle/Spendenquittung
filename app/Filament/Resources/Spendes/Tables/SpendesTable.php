<?php

namespace App\Filament\Resources\Spendes\Tables;

use App\Enums\SpendeStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SpendesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bescheinigungsnummer')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('spendendatum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('betrag')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('spender.nachname')
                    ->label('Spender')
                    ->formatStateUsing(fn ($record) => $record->spender?->vollname)
                    ->searchable(['nachname', 'vorname', 'firma'])
                    ->sortable(),

                TextColumn::make('foerderungszweck.name')
                    ->label('Zweck')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('ausstellungsdatum')
                    ->label('Ausgestellt')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('spendendatum', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SpendeStatus::class)
                    ->multiple(),
                SelectFilter::make('foerderungszweck_id')
                    ->label('Förderungszweck')
                    ->relationship('foerderungszweck', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn ($record) => $record->pdf_pfad
                        ? asset('storage/' . $record->pdf_pfad)
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->pdf_pfad !== null),
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
