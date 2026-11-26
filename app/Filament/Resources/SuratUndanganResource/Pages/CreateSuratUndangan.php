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
                $dasar = $survey->dasar_surat ?: 'Surat ...';
                $this->form->fill([
                    'survey_id'        => $survey->id,
                    'hal'              => 'Undangan ' . $survey->name,
                    'yth_teks'         => "Yth. Peserta {$survey->name}\nBPS Kabupaten Demak\ndi\n     tempat",
                    'paragraf_pembuka' => "Menindaklanjuti {$dasar}, dengan ini kami mengharapkan kehadiran Saudara pada:",
                    'agenda_acara'     => $survey->name,
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
