<?php

namespace App\Filament\Resources\SurveyUserResource\Pages;

use App\Filament\Resources\SurveyUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurveyUsers extends ListRecords
{
    protected static string $resource = SurveyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
