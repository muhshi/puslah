<?php

namespace App\Filament\Resources\OfficeResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\OfficeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOffices extends ListRecords
{
    protected static string $resource = OfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
