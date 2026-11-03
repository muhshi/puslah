<?php

namespace App\Filament\Resources\SharedWorkReportResource\Pages;

use App\Filament\Resources\SharedWorkReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSharedWorkReports extends ListRecords
{
    protected static string $resource = SharedWorkReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Wadah KIPAPP Baru 📁'),
        ];
    }
}
