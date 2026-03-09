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
            Actions\Action::make('create-bulk')
                ->label('Buat Kolektif')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(fn() => SuratTugasResource::getUrl('create-bulk')),
            Actions\Action::make('manage-blocked-numbers')
                ->label('Block Nomor')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->url(fn() => SuratTugasResource::getUrl('manage-blocked-numbers')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SuratTugasResource\Widgets\SkippedNumbersInfoWidget::class,
        ];
    }
}
