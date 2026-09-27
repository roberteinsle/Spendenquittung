<?php

namespace App\Filament\Resources\Protokolle;

use App\Filament\Resources\Protokolle\Pages\ListProtokolle;
use App\Filament\Resources\Protokolle\Tables\ProtokolleTable;
use App\Models\Protokolleintrag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProtokollResource extends Resource
{
    protected static ?string $model = Protokolleintrag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $slug = 'protokoll';

    protected static ?string $navigationLabel = 'Protokoll';

    protected static ?string $modelLabel = 'Protokolleintrag';

    protected static ?string $pluralModelLabel = 'Protokoll';

    protected static \UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 5;

    /**
     * Ein Prüfpfad, den jeder ändern oder auch nur einsehen kann, ist keiner.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->istAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ProtokolleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProtokolle::route('/'),
        ];
    }
}
