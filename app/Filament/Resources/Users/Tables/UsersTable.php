<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('login_pin')
                    ->label('PIN')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('ist_admin')
                    ->label('Verwaltung')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Locking yourself out of an app with no password reset is
                    // not recoverable from the browser.
                    ->hidden(fn ($record) => $record->is(auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
