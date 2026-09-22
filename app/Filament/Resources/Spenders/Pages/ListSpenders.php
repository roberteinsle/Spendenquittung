<?php

namespace App\Filament\Resources\Spenders\Pages;

use App\Filament\Resources\Spenders\SpenderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpenders extends ListRecords
{
    protected static string $resource = SpenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
