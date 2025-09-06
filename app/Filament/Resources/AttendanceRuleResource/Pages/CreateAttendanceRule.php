<?php

namespace App\Filament\Resources\AttendanceRuleResource\Pages;

use App\Filament\Resources\AttendanceRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceRule extends CreateRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['approved_by'] = Auth::id();
        return $data;
    }
}
