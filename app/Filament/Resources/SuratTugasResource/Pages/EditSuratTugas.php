<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSuratTugas extends EditRecord
{
    protected static string $resource = SuratTugasResource::class;

    public $is_sppd = false;
    public $sppdData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $sppd = $this->record->sppd;
        $data['is_sppd'] = $sppd !== null;
        if ($sppd) {
            $data['nomor_sppd'] = $sppd->nomor_sppd;
            $data['nomor_urut_sppd'] = $sppd->nomor_urut_sppd;
            $data['kode_klasifikasi_sppd'] = $sppd->kode_klasifikasi_sppd;
            $data['tingkat_perjalanan_dinas'] = $sppd->tingkat_perjalanan_dinas;
            $data['alat_angkutan'] = $sppd->alat_angkutan;
            $data['mak'] = $sppd->mak;
            $data['ppk_name'] = $sppd->ppk_name;
            $data['ppk_nip'] = $sppd->ppk_nip;
            $data['ppk_title'] = $sppd->ppk_title;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (\App\Models\SuratTugas::hasOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null, $this->record->id)) {
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

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->is_sppd) {
            $settings = app(\App\Settings\SystemSettings::class);
            $sppdData = $this->sppdData;
            if (empty($sppdData['ppk_name'])) {
                $sppdData['ppk_name'] = $settings->ppk_name;
                $sppdData['ppk_nip'] = $settings->ppk_nip;
                $sppdData['ppk_title'] = $settings->ppk_title;
            }
            $this->record->sppd()->updateOrCreate([], $sppdData);
        } else {
            $this->record->sppd()->delete();
        }
    }
}
