<?php

namespace App\Filament\Resources\SuratUndanganResource\Pages;

use App\Filament\Resources\SuratUndanganResource;
use App\Models\Survey;
use Filament\Resources\Pages\CreateRecord;

class CreateSuratUndangan extends CreateRecord
{
    protected static string $resource = SuratUndanganResource::class;

    public function mount(): void
    {
        parent::mount();

        $surveyId = request()->query('survey_id');
        if ($surveyId) {
            $survey = Survey::with('surveyUsers')->find($surveyId);
            if ($survey) {
                $rujukan = $survey->rujukan_surat ?: ($survey->dasar_surat ?: 'Surat ...');
                $this->form->fill([
                    'survey_id'        => $survey->id,
                    'hal'              => 'Undangan ' . $survey->name,
                    'yth_teks'         => "Yth. Peserta {$survey->name}\nBPS Kabupaten Demak\ndi\n     Tempat",
                    'paragraf_pembuka' => "Menindaklanjuti {$rujukan}, dengan ini kami mengharapkan kehadiran Saudara pada:",
                    'agenda_acara'     => $survey->name,
                    'agenda_tempat'    => $survey->lokasi,
                    'peserta_ids'      => $survey->surveyUsers->pluck('user_id')->toArray(),
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
