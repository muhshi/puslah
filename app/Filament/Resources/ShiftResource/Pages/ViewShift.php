<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
