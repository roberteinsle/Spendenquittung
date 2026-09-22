<?php

namespace App\Filament\Resources\Spenders;

use App\Filament\Resources\Spenders\Pages\CreateSpender;
use App\Filament\Resources\Spenders\Pages\EditSpender;
use App\Filament\Resources\Spenders\Pages\ListSpenders;
use App\Filament\Resources\Spenders\Schemas\SpenderForm;
use App\Filament\Resources\Spenders\Tables\SpendersTable;
use App\Models\Spender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpenderResource extends Resource
{
    protected static ?string $model = Spender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Spender';

    protected static ?string $modelLabel = 'Spender';

    protected static ?string $pluralModelLabel = 'Spender';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SpenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpendersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpenders::route('/'),
            'create' => CreateSpender::route('/create'),
            'edit' => EditSpender::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
