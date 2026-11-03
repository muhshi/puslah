<?php

namespace App\Filament\Resources\SharedWorkReportResource\Pages;

use App\Filament\Resources\SharedWorkReportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSharedWorkReport extends CreateRecord
{
    protected static string $resource = SharedWorkReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
