<?php

namespace App\Filament\Resources\DailyActivityResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\DailyActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyActivity extends EditRecord
{
    protected static string $resource = DailyActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
