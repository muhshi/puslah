<?php

namespace App\Services;

use App\Models\SuratUndangan;
use App\Models\SurveyUser;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SuratUndanganPdfService
{
    /**
     * Generate PDF DomPDF instance untuk Surat Undangan.
     */
    public function generate(SuratUndangan $suratUndangan): \Barryvdh\DomPDF\PDF
    {
        $suratUndangan->loadMissing(['kopSurat', 'survey', 'schedules']);

        // Persiapkan data kop
        $kopSurat = $suratUndangan->kopSurat;
        $logoBpsBase64 = $this->getImageBase64($kopSurat?->logo_kiri_path ?: 'images/kop/logo_bps_header.png');
        $logoSecondaryBase64 = $kopSurat?->logo_kanan_path ? $this->getImageBase64($kopSurat->logo_kanan_path) : null;

        // Persiapkan peserta per kelas jika lampiran 1 aktif
        $pesertaPerKelas = [];
        $isMultiClass = false;

        if ($suratUndangan->sertakan_lampiran_peserta) {
            $pesertaPerKelas = $this->resolvePesertaData($suratUndangan);
            $isMultiClass = count($pesertaPerKelas) > 1 || (count($pesertaPerKelas) === 1 && !isset($pesertaPerKelas['Peserta']));
        }

        // Persiapkan gambar jadwal jika mode upload
        $jadwalImageBase64 = null;
        if ($suratUndangan->sertakan_lampiran_jadwal && $suratUndangan->mode_jadwal === 'upload' && $suratUndangan->jadwal_image_path) {
            $jadwalImageBase64 = $this->getImageBase64($suratUndangan->jadwal_image_path);
        }

        $data = [
            'suratUndangan'       => $suratUndangan,
            'kopSurat'            => $kopSurat,
            'logoBpsBase64'       => $logoBpsBase64,
            'logoSecondaryBase64' => $logoSecondaryBase64,
            'pesertaPerKelas'     => $pesertaPerKelas,
            'isMultiClass'        => $isMultiClass,
            'jadwalImageBase64'   => $jadwalImageBase64,
        ];

        $pdf = Pdf::loadView('surat-undangan.pdf', $data)
            ->setPaper([0, 0, 612, 936], 'portrait') // Ukuran Folio / F4 (8.5 x 13 in)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf;
    }

    /**
     * Resolusi data peserta yang dikelompokkan per kelas atau flat.
     */
    public function resolvePesertaData(SuratUndangan $suratUndangan): array
    {
        $survey = $suratUndangan->survey;
        $selectedIds = is_array($suratUndangan->peserta_ids) && count($suratUndangan->peserta_ids) > 0
            ? $suratUndangan->peserta_ids
            : null;

        if (!$survey) {
            if ($selectedIds) {
                $users = User::with(['profile', 'roles'])->whereIn('id', $selectedIds)->get();
                return ['Peserta' => $users->map(fn ($u) => $this->formatPesertaRow($u, null))->toArray()];
            }
            return [];
        }

        $query = SurveyUser::where('survey_id', $survey->id)
            ->with(['class', 'user.profile', 'user.roles']);

        if ($selectedIds) {
            $query->whereIn('user_id', $selectedIds);
        }

        $surveyUsers = $query->get();

        $result = [];
        $hasClasses = ($survey->is_multiclass && $survey->classes()->count() > 0) || $survey->is_pelatihan;

        if ($hasClasses) {
            $grouped = $surveyUsers->groupBy(fn ($su) => $su->class?->name ?: ($su->kelas ?: 'Tanpa Kelas / Umum'));
            foreach ($grouped as $className => $items) {
                $pesertaList = [];
                foreach ($items as $su) {
                    if ($su->user) {
                        $pesertaList[] = $this->formatPesertaRow($su->user, $su->role_pelatihan ?: $su->role, $su);
                    }
                }
                if (count($pesertaList) > 0) {
                    $result[$className] = $pesertaList;
                }
            }

            uksort($result, function (string $a, string $b): int {
                if ($a === 'Tanpa Kelas / Umum') {
                    return 1;
                }
                if ($b === 'Tanpa Kelas / Umum') {
                    return -1;
                }

                return strnatcasecmp($a, $b);
            });
        } else {
            $pesertaList = [];
            foreach ($surveyUsers as $su) {
                if ($su->user) {
                    $pesertaList[] = $this->formatPesertaRow($su->user, $su->role ?: $su->role_pelatihan, $su);
                }
            }
            if (count($pesertaList) > 0) {
                $result['Peserta'] = $pesertaList;
            }
        }

        $roleOrder = $this->resolveRoleOrder($suratUndangan);
        foreach ($result as $className => &$pesertaList) {
            usort($pesertaList, fn (array $a, array $b): int => $this->comparePeserta($a, $b, $roleOrder));
        }
        unset($pesertaList);

        return $result;
    }

    /**
     * Build lowercase role => priority index map from SuratUndangan configuration.
     *
     * @return array<string, int>
     */
    protected function resolveRoleOrder(SuratUndangan $suratUndangan): array
    {
        $roles = $suratUndangan->urutan_role_peserta;
        if (! is_array($roles) || count($roles) === 0) {
            $roles = ['PPL', 'PML', 'Inda', 'Panitia'];
        }

        $order = [];
        foreach (array_values($roles) as $index => $role) {
            $normalized = mb_strtolower(trim((string) $role));
            if ($normalized !== '' && ! array_key_exists($normalized, $order)) {
                $order[$normalized] = $index;
            }
        }

        return $order;
    }

    /**
     * Compare two participant rows by configured role priority, then role name, then participant name.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @param  array<string, int>  $roleOrder
     */
    protected function comparePeserta(array $a, array $b, array $roleOrder): int
    {
        $roleA = mb_strtolower(trim((string) ($a['jabatan'] ?? '')));
        $roleB = mb_strtolower(trim((string) ($b['jabatan'] ?? '')));

        $prioA = $roleOrder[$roleA] ?? PHP_INT_MAX;
        $prioB = $roleOrder[$roleB] ?? PHP_INT_MAX;

        if ($prioA !== $prioB) {
            return $prioA <=> $prioB;
        }

        if ($prioA === PHP_INT_MAX && $roleA !== $roleB) {
            $roleCmp = strnatcasecmp((string) ($a['jabatan'] ?? ''), (string) ($b['jabatan'] ?? ''));
            if ($roleCmp !== 0) {
                return $roleCmp;
            }
        }

        return strnatcasecmp((string) ($a['nama'] ?? ''), (string) ($b['nama'] ?? ''));
    }

    /**
     * Format row informasi peserta.
     */
    protected function formatPesertaRow(User $user, ?string $surveyRole, ?SurveyUser $surveyUser = null): array
    {
        $profile = $user->profile;
        $jabatan = $surveyRole ?: ($profile?->jabatan ?: ($user->roles->first()?->name ?? 'Peserta'));

        return [
            'id'             => $user->id,
            'nama'           => $profile?->full_name ?: $user->name,
            'nip'            => $profile?->nip ?: ($profile?->old_nip ?: '-'),
            'jabatan'        => $jabatan,
            'asal_peserta'   => $surveyUser?->asal ?: '-',
            'asal_kecamatan' => $profile?->address ?: '-',
            'sobat_id'       => $profile?->sobat_id ?: '-',
            'email'          => $user->email ?: '-',
            'no_hp'          => $profile?->phone ?: '-',
        ];
    }

    /**
     * Dapatkan path absolut gambar lokal / storage untuk DomPDF 3.x.
     */
    protected function getImageBase64(string $path): ?string
    {
        // 1. Cek di storage disk public
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        // 2. Cek di public_path
        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // 3. Cek direct file_exists
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }
}
