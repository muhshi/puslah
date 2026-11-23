<?php

namespace App\Imports;

use App\Models\Survey;
use App\Models\SurveyClass;
use App\Models\SurveyUser;
use App\Models\User;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class SurveyClassAssignmentImport implements OnEachRow, WithHeadingRow
{
    protected Survey $survey;
    public int $success = 0;
    public int $skipped = 0;
    public int $newClassesCount = 0;
    protected array $classesCache = [];

    public function __construct(Survey $survey)
    {
        $this->survey = $survey;

        // Cache existing classes for fast lookup
        $this->survey->classes()->get()->each(function ($cls) {
            $this->classesCache[mb_strtolower(trim($cls->name))] = $cls->id;
        });
    }

    public function onRow(Row $row)
    {
        $data = $row->toArray();

        // Cari identifier: email, sobat_id, nama
        $email = !empty($data['email']) ? trim($data['email']) : null;
        $sobatId = !empty($data['sobat_id']) ? trim($data['sobat_id']) : null;
        $nama = !empty($data['nama']) ? trim($data['nama']) : null;
        $kelasRaw = !empty($data['kelas']) ? trim($data['kelas']) : null;

        if (!$email && !$sobatId && !$nama) {
            $this->skipped++;
            return;
        }

        // Cari User / SurveyUser di kegiatan ini
        $surveyUser = null;

        // 1. Cari via Email
        if ($email && $email !== '-') {
            $surveyUser = SurveyUser::where('survey_id', $this->survey->id)
                ->whereHas('user', fn ($q) => $q->where('email', strtolower($email)))
                ->first();
        }

        // 2. Cari via Sobat ID jika belum ketemu
        if (!$surveyUser && $sobatId) {
            $surveyUser = SurveyUser::where('survey_id', $this->survey->id)
                ->whereHas('user.profile', fn ($q) => $q->where('sobat_id', $sobatId))
                ->first();
        }

        // 3. Cari via Nama jika belum ketemu
        if (!$surveyUser && $nama && $nama !== '-') {
            $normalizedName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $nama)));
            $surveyUser = SurveyUser::where('survey_id', $this->survey->id)
                ->whereHas('user', function ($q) use ($normalizedName) {
                    $q->whereRaw('LOWER(name) = ?', [$normalizedName])
                      ->orWhereHas('profile', fn ($pq) => $pq->whereRaw('LOWER(full_name) = ?', [$normalizedName]));
                })
                ->first();
        }

        if (!$surveyUser) {
            $this->skipped++;
            return;
        }

        // Update sobat_id ke profile jika belum ada
        if ($sobatId) {
            $profile = $surveyUser->user?->profile;
            if ($profile && empty($profile->sobat_id)) {
                $profile->update(['sobat_id' => $sobatId]);
            }
        }

        // Resolusi Kelas
        $targetClassId = null;
        if (!empty($kelasRaw) && $kelasRaw !== '-') {
            $classKey = mb_strtolower(trim($kelasRaw));

            if (isset($this->classesCache[$classKey])) {
                $targetClassId = $this->classesCache[$classKey];
            } else {
                // Buat kelas baru secara otomatis
                $classNameProper = mb_convert_case(trim($kelasRaw), MB_CASE_TITLE, 'UTF-8');
                $newClass = $this->survey->classes()->create([
                    'name' => $classNameProper,
                ]);
                $targetClassId = $newClass->id;
                $this->classesCache[$classKey] = $targetClassId;
                $this->newClassesCount++;
            }
        }

        // Update kelas peserta
        $surveyUser->update(['survey_class_id' => $targetClassId]);
        $this->success++;
    }
}
