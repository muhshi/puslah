<?php

namespace App\Filament\Resources\LaporanLemburResource\Pages;

use App\Filament\Resources\LaporanLemburResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLaporanLembur extends CreateRecord
{
    protected static string $resource = LaporanLemburResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']) || empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
