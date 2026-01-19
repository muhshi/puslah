<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\SuratTugas;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuratTugas extends ListRecords
{
    protected static string $resource = SuratTugasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('create-bulk')
                ->label('Buat Kolektif')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(fn() => SuratTugasResource::getUrl('create-bulk')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SuratTugasResource\Widgets\SkippedNumbersInfoWidget::class,
        ];
    }
}
