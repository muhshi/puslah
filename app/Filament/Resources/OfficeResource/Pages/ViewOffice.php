<?php

namespace App\Filament\Resources\OfficeResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\OfficeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOffice extends ViewRecord
{
    protected static string $resource = OfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
