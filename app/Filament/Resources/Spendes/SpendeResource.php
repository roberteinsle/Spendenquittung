<?php

namespace App\Filament\Resources\Spendes;

use App\Filament\Resources\Spendes\Pages\CreateSpende;
use App\Filament\Resources\Spendes\Pages\EditSpende;
use App\Filament\Resources\Spendes\Pages\ListSpendes;
use App\Filament\Resources\Spendes\Schemas\SpendeForm;
use App\Filament\Resources\Spendes\Tables\SpendesTable;
use App\Models\Spende;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpendeResource extends Resource
{
    protected static ?string $model = Spende::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Bescheinigungen';

    protected static ?string $modelLabel = 'Bescheinigung';

    protected static ?string $pluralModelLabel = 'Bescheinigungen';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SpendeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpendesTable::configure($table);
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
            'index' => ListSpendes::route('/'),
            'create' => CreateSpende::route('/create'),
            'edit' => EditSpende::route('/{record}/edit'),
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
