<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;

class PelatihanPenilaianService
{
    public function __construct(
        protected SipetraUserDataService $sipetraService,
    ) {}

    /**
     * Return active penilaian component weights for the given survey.
     *
     * @return array<int, array{key: string, label: string, weight: float|int, required?: bool}>
     */
    public function getWeights(Survey $survey): array
    {
        $weights = $survey->penilaian_weights;
        if (! is_array($weights) || count($weights) === 0) {
            return Survey::DEFAULT_PENILAIAN_WEIGHTS;
        }

        return array_values(array_map(function (array $item): array {
            $label = trim((string) ($item['label'] ?? 'Komponen'));
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                $key = str($label)->slug('_')->toString();
            }

            return [
                'key' => $key,
                'label' => $label,
                'weight' => (float) ($item['weight'] ?? 0),
                'required' => (bool) ($item['required'] ?? false),
            ];
        }, $weights));
    }

    /**
     * Calculate weighted final score (0-100) from component values.
     *
     * @param  array<string, mixed>  $nilaiKomponen
     * @param  array<int, array{key: string, label: string, weight: float|int}>  $weights
     */
    public function calculateFinalScore(array $nilaiKomponen, array $weights): ?float
    {
        $hasValue = false;
        $total = 0.0;

        foreach ($weights as $comp) {
            $key = $comp['key'];
            $weight = (float) ($comp['weight'] ?? 0);
            $raw = $nilaiKomponen[$key] ?? null;

            if ($raw !== null && $raw !== '' && is_numeric($raw)) {
                $hasValue = true;
                $total += ((float) $raw) * ($weight / 100.0);
            }
        }

        if (! $hasValue) {
            return null;
        }

        return round($total, 2);
    }

    /**
     * Build complete penilaian dataset for a Pelatihan survey, separating Inda/Panitia from PPL/PML.
     *
     * @return array{
     *   weights: array<int, array{key: string, label: string, weight: float|int, required?: bool}>,
     *   classes: array<int, string>,
     *   class_officials: array<string, array{inda: array<int, string>, panitia: array<int, string>}>,
     *   rows: array<int, array<string, mixed>>
     * }
     */
    public function build(Survey $survey, ?string $kelasFilter = null): array
    {
        $survey->load(['surveyUsers.user.profile', 'surveyUsers.user.roles', 'surveyUsers.class']);

        $users = $survey->surveyUsers
            ->map(fn (SurveyUser $su) => $su->user)
            ->filter()
            ->unique('id');

        $sipetraData = $this->sipetraService->fetchForUsers($users);
        $weights = $this->getWeights($survey);

        $classOfficials = [];
        $rows = [];
        $allClasses = [];

        foreach ($survey->surveyUsers as $su) {
            $user = $su->user;
            if (! $user) {
                continue;
            }

            $kelas = $su->class?->name ?: ($su->kelas ?: 'Kelas A');
            $allClasses[$kelas] = $kelas;

            $role = trim((string) ($su->role_pelatihan ?: ($su->role ?: 'PPL')));
            $nama = $user->profile?->full_name ?: $user->name;

            if (strcasecmp($role, 'Inda') === 0) {
                $classOfficials[$kelas]['inda'][] = $nama;

                continue;
            }

            if (strcasecmp($role, 'Panitia') === 0) {
                $classOfficials[$kelas]['panitia'][] = $nama;

                continue;
            }

            if (filled($kelasFilter) && strcasecmp($kelas, $kelasFilter) !== 0) {
                continue;
            }

            $sipetra = $sipetraData[$user->id] ?? null;
            $profile = $user->profile;
            $isOrganik = $this->isOrganik($user);

            if ($isOrganik) {
                $nipNik = $profile?->nip
                    ?: ($sipetra->nip_baru ?? $sipetra->nip ?? $profile?->nik ?? $sipetra->nik ?? '');
                $instansi = 'Badan Pusat Statistik';
                $tipePeserta = 'internal';
            } else {
                $nipNik = $profile?->nik ?: ($sipetra->nik ?? '');
                $instansi = 'Mitra';
                $tipePeserta = 'eksternal';
            }

            $alamat = $profile?->address
                ?: ($sipetra->alamat ?? ($su->asal ?: ''));

            $nilaiKomponen = is_array($su->nilai_komponen) ? $su->nilai_komponen : [];
            $nilai = $su->score;

            $rows[] = [
                'survey_user_id' => $su->id,
                'user_id' => $user->id,
                'nip_nik' => trim((string) $nipNik),
                'nama' => $nama,
                'instansi' => $instansi,
                'alamat' => trim((string) $alamat),
                'tipe_peserta' => $tipePeserta,
                'kelas' => $kelas,
                'role' => $role,
                'nilai_komponen' => $nilaiKomponen,
                'nilai' => $nilai,
                'is_lulus' => 1,
            ];
        }

        $classes = array_values($allClasses);
        usort($classes, 'strnatcasecmp');

        usort($rows, function (array $a, array $b): int {
            $classCmp = strnatcasecmp($a['kelas'], $b['kelas']);
            if ($classCmp !== 0) {
                return $classCmp;
            }

            $rolePriority = ['ppl' => 0, 'pml' => 1];
            $pA = $rolePriority[strtolower($a['role'])] ?? 99;
            $pB = $rolePriority[strtolower($b['role'])] ?? 99;
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }

            return strnatcasecmp($a['nama'], $b['nama']);
        });

        return [
            'weights' => $weights,
            'classes' => $classes,
            'class_officials' => $classOfficials,
            'rows' => $rows,
        ];
    }

    protected function isOrganik(User $user): bool
    {
        if ($user->hasRole('Organik')) {
            return true;
        }

        if ($user->hasRole('Mitra')) {
            return false;
        }

        return filled($user->profile?->nip);
    }
}
