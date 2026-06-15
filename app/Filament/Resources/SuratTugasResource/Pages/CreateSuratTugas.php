<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSuratTugas extends CreateRecord
{
    protected static string $resource = SuratTugasResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (\App\Models\SuratTugas::hasOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'waktu_mulai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
                'waktu_selesai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
            ]);
        }

        $data['created_by'] = auth()->id();
        return $data;
    }
}
