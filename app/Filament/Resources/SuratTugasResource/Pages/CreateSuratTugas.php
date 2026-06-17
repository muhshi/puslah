<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSuratTugas extends CreateRecord
{
    protected static string $resource = SuratTugasResource::class;

    public $is_sppd = false;
    public $sppdData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (\App\Models\SuratTugas::hasOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'waktu_mulai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
                'waktu_selesai' => 'Tanggal tugas overlap (tumpang tindih) dengan surat tugas pegawai ini di survey yang sama.',
            ]);
        }

        $this->is_sppd = $data['is_sppd'] ?? false;
        $this->sppdData = [
            'nomor_sppd' => $data['nomor_sppd'] ?? null,
            'nomor_urut_sppd' => $data['nomor_urut_sppd'] ?? null,
            'kode_klasifikasi_sppd' => $data['kode_klasifikasi_sppd'] ?? null,
            'tingkat_perjalanan_dinas' => $data['tingkat_perjalanan_dinas'] ?? null,
            'alat_angkutan' => $data['alat_angkutan'] ?? null,
            'mak' => $data['mak'] ?? null,
            'ppk_name' => $data['ppk_name'] ?? null,
            'ppk_nip' => $data['ppk_nip'] ?? null,
            'ppk_title' => $data['ppk_title'] ?? null,
        ];
        
        unset($data['is_sppd'], $data['nomor_sppd'], $data['nomor_urut_sppd'], $data['kode_klasifikasi_sppd'], $data['tingkat_perjalanan_dinas'], $data['alat_angkutan'], $data['mak'], $data['ppk_name'], $data['ppk_nip'], $data['ppk_title']);

        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->is_sppd) {
            $settings = app(\App\Settings\SystemSettings::class);
            $sppdData = $this->sppdData;
            if (empty($sppdData['ppk_name'])) {
                $sppdData['ppk_name'] = $settings->ppk_name;
                $sppdData['ppk_nip'] = $settings->ppk_nip;
                $sppdData['ppk_title'] = $settings->ppk_title;
            }
            $this->record->sppd()->create($sppdData);
        }
    }
}
