<?php

namespace App\Filament\Resources\Foerderungszwecks\Pages;

use App\Filament\Resources\Foerderungszwecks\FoerderungszweckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFoerderungszwecks extends ListRecords
{
    protected static string $resource = FoerderungszweckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
