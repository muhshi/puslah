<?php

namespace App\Filament\Resources\SharedWorkReportResource\Pages;

use App\Filament\Resources\SharedWorkReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSharedWorkReport extends EditRecord
{
    protected static string $resource = SharedWorkReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
