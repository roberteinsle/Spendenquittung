<?php

namespace App\Filament\Resources\Spendes\Pages;

use App\Filament\Resources\Spendes\SpendeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpendes extends ListRecords
{
    protected static string $resource = SpendeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
