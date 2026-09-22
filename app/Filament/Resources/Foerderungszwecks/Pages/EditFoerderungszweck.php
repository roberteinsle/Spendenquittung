<?php

namespace App\Filament\Resources\Foerderungszwecks\Pages;

use App\Filament\Resources\Foerderungszwecks\FoerderungszweckResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFoerderungszweck extends EditRecord
{
    protected static string $resource = FoerderungszweckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
