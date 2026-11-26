<?php

namespace App\Filament\Resources\KopSuratResource\Pages;

use App\Filament\Resources\KopSuratResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKopSurat extends EditRecord
{
    protected static string $resource = KopSuratResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
