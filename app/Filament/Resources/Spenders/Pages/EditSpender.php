<?php

namespace App\Filament\Resources\Spenders\Pages;

use App\Filament\Resources\Spenders\SpenderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSpender extends EditRecord
{
    protected static string $resource = SpenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
