<?php

namespace App\Filament\Resources\AttendanceRuleResource\Pages;

use App\Filament\Resources\AttendanceRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAttendanceRule extends EditRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // kalau mau update approver saat edit, aktifkan ini
        $data['approved_by'] = Auth::id();
        return $data;
    }
}
