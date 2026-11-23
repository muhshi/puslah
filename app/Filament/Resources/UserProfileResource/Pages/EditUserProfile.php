<?php

namespace App\Filament\Resources\UserProfileResource\Pages;

use App\Filament\Resources\UserProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserProfile extends EditRecord
{
    protected static string $resource = UserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit_in_sipetra')
                ->label('Kelola di SIPETRA (SSOT)')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(fn (): string => rtrim(config('services.sipetra.base_url', 'https://bpsdemak.com'), '/') . '/account')
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
