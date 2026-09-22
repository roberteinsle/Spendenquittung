<?php

namespace App\Filament\Resources\Spendes\Pages;

use App\Filament\Resources\Spendes\SpendeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSpende extends EditRecord
{
    protected static string $resource = SpendeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
