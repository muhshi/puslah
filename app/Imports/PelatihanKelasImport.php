<?php

namespace App\Imports;

use App\Models\Survey;
use App\Models\SurveyUser;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class PelatihanKelasImport implements OnEachRow, WithHeadingRow
{
    public int $success = 0;

    public int $skipped = 0;

    public int $failed = 0;

    public function __construct(protected Survey $survey) {}

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        try {
            $id = $data['id'] ?? $data['survey_user_id'] ?? null;
            if (! $id) {
                $this->skipped++;

                return;
            }

            // Ensure participant belongs to this survey for data integrity and tenant isolation
            $surveyUser = SurveyUser::where('survey_id', $this->survey->id)->find($id);
            if (! $surveyUser) {
                $this->skipped++;

                return;
            }

            $role = null;
            foreach (['role_pelatihan', 'role_ppl_pml_inda_panitia', 'role_pplpmlindapanitia', 'role', 'peran'] as $key) {
                if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                    $role = trim((string) $data[$key]);
                    break;
                }
            }

            $kelas = isset($data['kelas']) && trim((string) $data['kelas']) !== '' ? trim((string) $data['kelas']) : null;
            $asal = isset($data['asal']) && trim((string) $data['asal']) !== '' ? trim((string) $data['asal']) : null;
            $tujuan = isset($data['tujuan']) && trim((string) $data['tujuan']) !== '' ? trim((string) $data['tujuan']) : null;

            $updates = [];
            if ($role !== null) {
                $updates['role_pelatihan'] = $role;
            }
            if ($kelas !== null) {
                $updates['kelas'] = $kelas;
            }
            if ($asal !== null) {
                $updates['asal'] = $asal;
            }
            if ($tujuan !== null) {
                $updates['tujuan'] = $tujuan;
            }

            if (! empty($updates)) {
                $surveyUser->update($updates);
                $this->success++;
            } else {
                $this->skipped++;
            }
        } catch (\Throwable $e) {
            $this->failed++;
        }
    }
}
