<?php

namespace App\Filament\Resources\SharedWorkReportResource\Pages;

use App\Filament\Resources\SharedWorkReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListSharedWorkReports extends ListRecords
{
    protected static string $resource = SharedWorkReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Wadah KIPAPP Baru 📁')
                ->slideOver()
                ->modalHeading('Buat Wadah Butir KIPAPP Baru')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    return $data;
                }),
        ];
    }
}
