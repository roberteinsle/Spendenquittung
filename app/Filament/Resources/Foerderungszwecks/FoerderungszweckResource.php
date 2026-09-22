<?php

namespace App\Filament\Resources\Foerderungszwecks;

use App\Filament\Resources\Foerderungszwecks\Pages\CreateFoerderungszweck;
use App\Filament\Resources\Foerderungszwecks\Pages\EditFoerderungszweck;
use App\Filament\Resources\Foerderungszwecks\Pages\ListFoerderungszwecks;
use App\Filament\Resources\Foerderungszwecks\Schemas\FoerderungszweckForm;
use App\Filament\Resources\Foerderungszwecks\Tables\FoerderungszwecksTable;
use App\Models\Foerderungszweck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FoerderungszweckResource extends Resource
{
    protected static ?string $model = Foerderungszweck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Förderungszwecke';

    protected static ?string $modelLabel = 'Förderungszweck';

    protected static ?string $pluralModelLabel = 'Förderungszwecke';

    protected static \UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return FoerderungszweckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoerderungszwecksTable::configure($table);
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
            'index' => ListFoerderungszwecks::route('/'),
            'create' => CreateFoerderungszweck::route('/create'),
            'edit' => EditFoerderungszweck::route('/{record}/edit'),
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
