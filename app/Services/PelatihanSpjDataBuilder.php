<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Settings\SystemSettings;
use Carbon\Carbon;

class PelatihanSpjDataBuilder
{
    public function __construct(
        protected SipetraUserDataService $sipetraService,
        protected SystemSettings $settings,
    ) {}

    /**
     * Build unified SPJ, Invitation, and Attendance data structure for a Pelatihan survey.
     *
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        $survey->loadMissing([
            'surveyUsers.user.profile',
            'penyelenggara.profile',
            'ppk.profile',
            'bendahara.profile',
            'suratTugas',
        ]);

        // 1. Duration and Dates calculation (Inclusive)
        $startDate = $survey->start_date ? Carbon::parse($survey->start_date)->locale('id') : now()->locale('id');
        $endDate = $survey->end_date ? Carbon::parse($survey->end_date)->locale('id') : $startDate->copy();
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $jumlahHari = max(1, (int) $startDate->diffInDays($endDate) + 1);

        $dates = [];
        $curr = $startDate->copy();
        while ($curr->lte($endDate)) {
            $dates[] = $curr->copy();
            $curr->addDay();
        }
        if (empty($dates)) {
            $dates = [$startDate->copy()];
        }

        $periode = SuratTugasPdfService::formatPeriodeTugas($startDate, $endDate);

        // 2. Active SuratTugas
        $suratTugas = $survey->suratTugas()->latest('id')->first();
        $nomorSuratTugas = $suratTugas?->nomor_surat ?? '-';
        $tanggalSuratTugas = $suratTugas?->tanggal
            ? Carbon::parse($suratTugas->tanggal)->translatedFormat('d F Y')
            : ($survey->start_date ? Carbon::parse($survey->start_date)->translatedFormat('d F Y') : date('d F Y'));

        // 3. Batch lookup SIPETRA bank & NIK data
        $users = $survey->surveyUsers
            ->map(fn (SurveyUser $su) => $su->user)
            ->filter()
            ->unique('id');

        $sipetraData = $this->sipetraService->fetchForUsers($users);

        // 4. Officials Resolution (SystemSettings as primary SSOT, with legacy per-survey fallback)
        $kepalaName = $this->settings->cert_signer_name ?: 'Khomarudin, S.ST';
        $kepalaNip = $this->settings->cert_signer_nip ?: '197512091999011001';
        $kepalaTitle = $this->settings->cert_signer_title ?: 'Kepala Badan Pusat Statistik Kabupaten Demak';

        [$ppkName, $ppkNip, $ppkTitle] = $this->resolveOfficial(
            settingName: $this->settings->ppk_name ?? null,
            settingNip: $this->settings->ppk_nip ?? null,
            settingTitle: $this->settings->ppk_title ?? null,
            fallbackUser: $survey->ppk,
            fallbackTitle: 'Pejabat Pembuat Komitmen',
            defaultTitle: 'Pejabat Pembuat Komitmen',
        );

        [$bendaharaName, $bendaharaNip, $bendaharaTitle] = $this->resolveOfficial(
            settingName: $this->settings->bendahara_name ?? null,
            settingNip: $this->settings->bendahara_nip ?? null,
            settingTitle: $this->settings->bendahara_title ?? null,
            fallbackUser: $survey->bendahara,
            fallbackTitle: 'Bendahara Pengeluaran',
            defaultTitle: 'Bendahara Pengeluaran',
        );

        [$penyelenggaraName, $penyelenggaraNip, $penyelenggaraTitle] = $this->resolveOfficial(
            settingName: $this->settings->kasubag_umum_name ?? null,
            settingNip: $this->settings->kasubag_umum_nip ?? null,
            settingTitle: $this->settings->kasubag_umum_title ?? null,
            fallbackUser: $survey->penyelenggara,
            fallbackTitle: $survey->penyelenggara?->profile?->jabatan ?: 'Kepala Subbagian Umum',
            defaultTitle: 'Kepala Subbagian Umum',
        );

        // 5. Participants processing
        $allClassesRaw = [];
        $attendanceClassesRaw = [];
        $indas = [];
        $paymentRows = [];

        $pRowIndex = 0;
        foreach ($survey->surveyUsers as $su) {
            $user = $su->user;
            $sipetra = $user ? ($sipetraData[$user->id] ?? null) : null;

            $nama = $user?->profile?->full_name ?: $user?->name ?: '-';
            $nip = $user?->profile?->nip ?: ($sipetra->nip ?? $sipetra->nip_baru ?? '-');
            $nik = $sipetra->nik ?? $user?->profile?->nik ?? '-';
            $nikOrNip = ($nip && $nip !== '-') ? $nip : $nik;

            $bank = $sipetra->nama_bank ?? '-';
            $rekening = $sipetra->nomor_rekening ?? '-';
            $rekeningNama = $sipetra->nama_pemilik_rekening ?? $nama;

            $asal = $su->asal ?: $this->sipetraService->extractAsal($sipetra, $user, 'Demak');
            $tujuan = $su->tujuan ?: ($survey->kota_kegiatan ?? 'Demak');
            $role = $su->role_pelatihan ?: 'PPL';
            $kelas = $su->kelas ?: 'Kelas A';

            $transport = $su->transport_override ?? $survey->biaya_transport ?? 0;
            $uangHarian = $su->uang_harian_override ?? $survey->biaya_uang_harian ?? 0;
            $total = (int) $transport + ((int) $uangHarian * (int) $jumlahHari);

            $pRowIndex++;
            $paymentRow = [
                'no' => $pRowIndex,
                'user_id' => $user?->id,
                'user' => $user,
                'survey_user' => $su,
                'nama' => $nama,
                'nik' => $nik,
                'nip' => $nip,
                'nik_or_nip' => $nikOrNip,
                'bank' => $bank,
                'rekening' => $rekening,
                'rekening_nama' => $rekeningNama,
                'asal' => $asal,
                'tujuan' => $tujuan,
                'hari' => $jumlahHari,
                'transport' => (int) $transport,
                'uang_harian' => (int) $uangHarian,
                'total' => (int) $total,
                'role_pelatihan' => $role,
                'kelas' => $kelas,
            ];
            $paymentRows[] = $paymentRow;

            // Participant item for class lists
            $classItem = [
                'user' => $user,
                'survey_user' => $su,
                'nama' => $nama,
                'asal' => $asal,
                'bertugas_sebagai' => $role,
                'role_pelatihan' => $role,
                'kelas' => $kelas,
                'nip' => $nip,
                'nik' => $nik,
                'nik_or_nip' => $nikOrNip,
            ];

            $allClassesRaw[$kelas][] = $classItem;

            if ($role === 'Inda') {
                $indas[] = $classItem;
            } else {
                $attendanceClassesRaw[$kelas][] = $classItem;
            }
        }

        // Format all_classes grouped and sorted naturally
        uksort($allClassesRaw, 'strnatcasecmp');
        $allClasses = [];
        foreach ($allClassesRaw as $kelasNama => $rows) {
            foreach ($rows as $i => &$row) {
                $row['no'] = $i + 1;
            }
            unset($row);
            $allClasses[] = [
                'kelas_nama' => $kelasNama,
                'rows' => $rows,
            ];
        }

        // Format attendance_classes grouped and sorted naturally
        uksort($attendanceClassesRaw, 'strnatcasecmp');
        $attendanceClasses = [];
        foreach ($attendanceClassesRaw as $kelasNama => $rows) {
            foreach ($rows as $i => &$row) {
                $row['no'] = $i + 1;
            }
            unset($row);
            $attendanceClasses[] = [
                'kelas_nama' => $kelasNama,
                'rows' => $rows,
            ];
        }

        // If attendanceClasses is empty (e.g. no participants or only inda), ensure at least empty or all_classes fallback
        if (empty($attendanceClasses) && ! empty($allClasses)) {
            $attendanceClasses = $allClasses;
        }

        // First Inda or default fallback
        $firstInda = $indas[0] ?? null;

        // 6. Calculations
        $grandTotal = (int) array_sum(array_column($paymentRows, 'total'));
        $terbilang = SppdService::terbilang($grandTotal).' rupiah';

        return [
            'survey' => $survey,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'jumlah_hari' => $jumlahHari,
            'dates' => $dates,
            'periode' => $periode,
            'lokasi' => $survey->lokasi ?? 'BPS Kabupaten Demak',
            'kota_kegiatan' => $survey->kota_kegiatan ?? 'Demak',
            'surat_tugas' => $suratTugas,
            'nomor_surat_tugas' => $nomorSuratTugas,
            'tanggal_surat_tugas' => $tanggalSuratTugas,
            'kepala' => [
                'name' => $kepalaName,
                'nip' => $kepalaNip,
                'title' => $kepalaTitle,
            ],
            'ppk' => [
                'name' => $ppkName,
                'nip' => $ppkNip,
                'title' => $ppkTitle,
            ],
            'bendahara' => [
                'name' => $bendaharaName,
                'nip' => $bendaharaNip,
                'title' => $bendaharaTitle,
            ],
            'penyelenggara' => [
                'name' => $penyelenggaraName,
                'nip' => $penyelenggaraNip,
                'title' => $penyelenggaraTitle,
            ],
            'all_classes' => $allClasses,
            'attendance_classes' => $attendanceClasses,
            'indas' => $indas,
            'first_inda' => $firstInda,
            'payment_rows' => $paymentRows,
            'grand_total' => $grandTotal,
            'terbilang' => $terbilang,
            'dipa' => [
                'program' => $survey->program ?: 'PROGRAM PENYEDIAAN DAN PELAYANAN INFORMASI STATISTIK (054.01.GG)',
                'kegiatan' => $survey->kegiatan_dipa ?: 'PENYEDIAAN DAN PENGEMBANGAN STATISTIK HARGA (2903)',
                'klasifikasi_ro' => $survey->klasifikasi_ro ?: 'DATA DAN INFORMASI PUBLIK (2903.BMA)',
                'rincian_output' => $survey->rincian_output ?: 'PUBLIKASI/LAPORAN SPKRT (2903.BMA.007)',
                'komponen' => $survey->komponen ?: 'PERSIAPAN (2903.BMA.009.051A)',
                'mak' => $survey->mak ?: 'BELANJA PERJALANAN DINAS DALAM KOTA (524113)',
            ],
            'atk_items' => $survey->atk_items ?: ['Totebag', 'Pulpen', 'Pensil 2B', 'Penghapus 2 buah', 'Rautan 1 buah', 'Blocknote'],
            'include_atk' => (bool) $survey->include_atk,
        ];
    }

    /**
     * Resolve official name, NIP, and title from SystemSettings when configured,
     * falling back to legacy per-survey User relation if settings are unset.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveOfficial(
        ?string $settingName,
        ?string $settingNip,
        ?string $settingTitle,
        ?User $fallbackUser,
        string $fallbackTitle,
        string $defaultTitle,
    ): array {
        if (filled($settingName) && trim($settingName) !== '-') {
            return [
                $settingName,
                filled($settingNip) ? $settingNip : '-',
                filled($settingTitle) ? $settingTitle : $defaultTitle,
            ];
        }

        if ($fallbackUser) {
            return [
                $fallbackUser->profile?->full_name ?: $fallbackUser->name,
                $fallbackUser->profile?->nip ?: '-',
                $fallbackTitle,
            ];
        }

        return [
            '-',
            '-',
            filled($settingTitle) ? $settingTitle : $defaultTitle,
        ];
    }
}
