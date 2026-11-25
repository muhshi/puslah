<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSuratTugas extends CreateRecord
{
    protected static string $resource = SuratTugasResource::class;

    public $is_sppd = false;
    public $sppdData = [];

    public function mount(): void
    {
        parent::mount();

        $surveyId = request()->integer('survey_id');
        if ($surveyId > 0 && ($survey = Survey::find($surveyId))) {
            $state = $this->form->getRawState();
            $state['survey_id'] = $survey->id;
            $state['keperluan'] = $survey->name;
            if ($survey->start_date) {
                $state['waktu_mulai'] = Carbon::parse($survey->start_date)->format('Y-m-d');
            }
            if ($survey->end_date) {
                $state['waktu_selesai'] = Carbon::parse($survey->end_date)->format('Y-m-d');
            }
            if (filled($survey->lokasi)) {
                $state['tempat_tugas'] = $survey->lokasi;
            }
            $this->form->fill($state);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $abaikanValidasi = $data['abaikan_validasi'] ?? false;
        
        if (!$abaikanValidasi && ($overlap = SuratTugas::getOverlap($data['user_id'], $data['survey_id'] ?? null, $data['waktu_mulai'] ?? null, $data['waktu_selesai'] ?? null))) {
            $user = User::find($data['user_id']);
            $userName = $user ? $user->name : 'Pegawai ini';
            $overlapMsg = SuratTugas::formatOverlapMessage($userName, $overlap);
            throw ValidationException::withMessages([
                'waktu_mulai' => $overlapMsg,
                'waktu_selesai' => $overlapMsg,
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
            'maksud_perjalanan' => $data['maksud_perjalanan'] ?? null,
            'tempat_berangkat' => $data['tempat_berangkat'] ?? null,
            'tempat_tujuan' => $data['tempat_tujuan'] ?? null,
        ];
        
        unset($data['abaikan_validasi'], $data['is_sppd'], $data['nomor_sppd'], $data['nomor_urut_sppd'], $data['kode_klasifikasi_sppd'], $data['tingkat_perjalanan_dinas'], $data['alat_angkutan'], $data['mak'], $data['ppk_name'], $data['ppk_nip'], $data['ppk_title'], $data['maksud_perjalanan'], $data['tempat_berangkat'], $data['tempat_tujuan']);

        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->is_sppd) {
            $settings = app(SystemSettings::class);
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
