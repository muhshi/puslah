<?php

namespace App\Filament\Resources\KopSuratResource\Pages;

use App\Filament\Resources\KopSuratResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKopSurats extends ListRecords
{
    protected static string $resource = KopSuratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
