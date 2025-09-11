<?php

namespace App\Filament\Resources\SurveyUserResource\Pages;

use App\Filament\Resources\SurveyUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSurveyUser extends EditRecord
{
    protected static string $resource = SurveyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
