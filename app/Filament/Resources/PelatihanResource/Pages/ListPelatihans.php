<?php

namespace App\Filament\Resources\PelatihanResource\Pages;

use App\Filament\Resources\PelatihanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPelatihans extends ListRecords
{
    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pelatihan'),
        ];
    }
}
