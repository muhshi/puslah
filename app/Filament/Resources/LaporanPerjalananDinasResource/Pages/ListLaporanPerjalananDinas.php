<?php

namespace App\Filament\Resources\LaporanPerjalananDinasResource\Pages;

use App\Filament\Resources\LaporanPerjalananDinasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLaporanPerjalananDinas extends ListRecords
{
    protected static string $resource = LaporanPerjalananDinasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
