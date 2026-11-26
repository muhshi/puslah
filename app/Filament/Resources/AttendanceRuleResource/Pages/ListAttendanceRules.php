<?php

namespace App\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\AttendanceRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRules extends ListRecords
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
