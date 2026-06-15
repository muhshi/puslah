<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSuratTugas extends EditRecord
{
    protected static string $resource = SuratTugasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (\App\Models\SuratTugas::hasOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null, $this->record->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'waktu_mulai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
                'waktu_selesai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
            ]);
        }

        return $data;
    }
}
