<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('presensi')
                ->url(route('presensi'))
                ->color('warning'),
            CreateAction::make(),
        ];
    }
}
