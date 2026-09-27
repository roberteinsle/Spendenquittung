<?php

namespace App\Filament\Resources\Protokolle\Pages;

use App\Filament\Resources\Protokolle\ProtokollResource;
use Filament\Resources\Pages\ListRecords;

class ListProtokolle extends ListRecords
{
    protected static string $resource = ProtokollResource::class;

    public function getTitle(): string
    {
        return 'Protokoll';
    }
}
