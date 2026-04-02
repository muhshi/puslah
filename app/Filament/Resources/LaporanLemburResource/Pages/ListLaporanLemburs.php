<?php

namespace App\Filament\Resources\LaporanLemburResource\Pages;

use App\Filament\Resources\LaporanLemburResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLaporanLemburs extends ListRecords
{
    protected static string $resource = LaporanLemburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
