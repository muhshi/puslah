<?php

namespace App\Services;

use App\Models\SuratTugas;
use App\Models\Sppd;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SppdService
{
    /**
     * Konversi nominal angka ke teks terbilang bahasa Indonesia.
     */
    public static function terbilang(int|float|string $angka): string
    {
        return preg_replace('/\s+/', ' ', trim(self::rawTerbilang($angka)));
    }

    private static function rawTerbilang(int|float|string $angka): string
    {
        $angka = abs((int) $angka);
        $baca = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
        $terbilang = "";

        if ($angka < 12) {
            $terbilang = " " . $baca[$angka];
        } elseif ($angka < 20) {
            $terbilang = self::rawTerbilang($angka - 10) . " belas";
        } elseif ($angka < 100) {
            $terbilang = self::rawTerbilang($angka / 10) . " puluh" . self::rawTerbilang($angka % 10);
        } elseif ($angka < 200) {
            $terbilang = " seratus" . self::rawTerbilang($angka - 100);
        } elseif ($angka < 1000) {
            $terbilang = self::rawTerbilang($angka / 100) . " ratus" . self::rawTerbilang($angka % 100);
        } elseif ($angka < 2000) {
            $terbilang = " seribu" . self::rawTerbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $terbilang = self::rawTerbilang($angka / 1000) . " ribu" . self::rawTerbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $terbilang = self::rawTerbilang($angka / 1000000) . " juta" . self::rawTerbilang($angka % 1000000);
        }

        return $terbilang;
    }

    /**
     * Format string nomor SPPD otomatis.
     */
    public static function formatNomorSppd(int|string $nomorUrut, ?string $klasifikasi = 'KP.650', ?int $year = null): string
    {
        $settings = app(SystemSettings::class);
        $prefix = $settings->surat_prefix ?? 'B';
        $office = $settings->office_code ?? '33210';
        $urut = str_pad((string) $nomorUrut, 4, '0', STR_PAD_LEFT);
        $klasifikasi = $klasifikasi ?: 'KP.650';
        $year = $year ?: now()->year;

        return "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
    }

    /**
     * Buat single record SPPD untuk SuratTugas.
     */
    public function createSppd(SuratTugas $record, array $data): Sppd
    {
        $settings = app(SystemSettings::class);

        return $record->sppd()->create([
            'nomor_sppd' => $data['nomor_sppd'],
            'nomor_urut_sppd' => $data['nomor_urut_sppd'],
            'kode_klasifikasi_sppd' => $data['kode_klasifikasi_sppd'] ?? 'KP.650',
            'tingkat_perjalanan_dinas' => $data['tingkat_perjalanan_dinas'] ?? 'C',
            'alat_angkutan' => $data['alat_angkutan'] ?? 'Kendaraan Pribadi',
            'mak' => $data['mak'] ?? '054.01.GG.2902.006.005.A.524113',
            'maksud_perjalanan' => $data['maksud_perjalanan'] ?? "Transport lokal dalam rangka {$record->keperluan}",
            'tempat_berangkat' => $data['tempat_berangkat'] ?? 'Demak',
            'tempat_tujuan' => $data['tempat_tujuan'] ?? ($record->tempat_tugas ?? '-'),
            'biaya_transport' => $data['biaya_transport'] ?? 170000,
            'ppk_name' => $settings->ppk_name,
            'ppk_nip' => $settings->ppk_nip,
            'ppk_title' => $settings->ppk_title,
        ]);
    }

    /**
     * Generate SPPD secara massal untuk collection SuratTugas.
     */
    public function generateBulkSppd(Collection $records, array $data): int
    {
        $settings = app(SystemSettings::class);
        $count = 0;
        $nextSppdUrut = ((int) $data['nomor_urut_sppd_mulai']) - 1;

        foreach ($records as $record) {
            if (!$record->sppd()->exists()) {
                $nextSppdUrut++;
                $urutSppdPad = str_pad((string) $nextSppdUrut, 4, '0', STR_PAD_LEFT);
                $nomorSppd = str_replace('{urut}', $urutSppdPad, $data['format_nomor_sppd']);

                $record->sppd()->create([
                    'nomor_sppd' => $nomorSppd,
                    'nomor_urut_sppd' => $nextSppdUrut,
                    'kode_klasifikasi_sppd' => 'KP.650',
                    'tingkat_perjalanan_dinas' => $data['tingkat_perjalanan_dinas'],
                    'alat_angkutan' => $data['alat_angkutan'],
                    'mak' => $data['mak'],
                    'maksud_perjalanan' => $data['maksud_perjalanan'] ?: "Transport lokal dalam rangka {$record->keperluan}",
                    'tempat_berangkat' => $data['tempat_berangkat'] ?: 'Demak',
                    'tempat_tujuan' => $data['tempat_tujuan'] ?: $record->tempat_tugas,
                    'biaya_transport' => $data['biaya_transport'],
                    'ppk_name' => $settings->ppk_name,
                    'ppk_nip' => $settings->ppk_nip,
                    'ppk_title' => $settings->ppk_title,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Download dokumen Word SPPD untuk SuratTugas.
     */
    public function downloadWord(SuratTugas $record): ?BinaryFileResponse
    {
        $settings = app(SystemSettings::class);
        $templatePath = $settings->sppd_template_path;

        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
            Notification::make()
                ->title('Template SPPD belum diupload di Pengaturan Sistem')
                ->danger()
                ->send();
            return null;
        }

        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));
        $sppd = $record->sppd;

        $template->setValue('nomor_sppd', $sppd->nomor_sppd);
        $ppkName = $sppd->ppk_name ?? $settings->ppk_name;
        $ppkNip = $sppd->ppk_nip ?? $settings->ppk_nip;

        $template->setValue('nama_ppk', $ppkName);
        $template->setValue('nip_ppk', $ppkNip);

        $template->setValue('nama_kepala', $record->signer_name ?? $settings->cert_signer_name);
        $template->setValue('nip_kepala', $record->signer_nip ?? $settings->cert_signer_nip);
        $template->setValue('nomor_surat', $record->nomor_surat);

        $template->setValue('nama_pegawai', $record->user->profile->full_name ?? $record->user->name);
        $template->setValue('nip_pegawai', $record->user->profile->nip ?? '-');
        $template->setValue('pangkat_golongan', $record->user->profile->pangkat_golongan ?? '-');
        $template->setValue('jabatan_pegawai', $record->user->profile->jabatan ?? '-');
        $template->setValue('jabatan', $record->user->profile->jabatan ?? '-');
        $template->setValue('unit_kerja', 'Badan Pusat Statistik Kabupaten Demak');

        $template->setValue('tingkat_perjalanan', $sppd->tingkat_perjalanan_dinas ?? '-');
        $template->setValue('maksud_perjalanan', $sppd->maksud_perjalanan ?? "Transport lokal dalam rangka {$record->keperluan}");
        $template->setValue('keperluan', $record->keperluan);
        $template->setValue('alat_angkutan', $sppd->alat_angkutan ?? '-');
        $template->setValue('tempat_berangkat', $sppd->tempat_berangkat ?? 'Demak');
        $template->setValue('tempat_tujuan', $sppd->tempat_tujuan ?? $record->tempat_tugas ?? '-');

        $start = Carbon::parse($record->waktu_mulai);
        $end = Carbon::parse($record->waktu_selesai);
        $lama = $start->diffInDays($end) + 1;
        $terbilangMap = [
            1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
            6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan', 10 => 'sepuluh',
            11 => 'sebelas', 12 => 'dua belas', 13 => 'tiga belas', 14 => 'empat belas',
            15 => 'lima belas', 30 => 'tiga puluh', 31 => 'tiga puluh satu'
        ];
        $lamaText = $terbilangMap[$lama] ?? $lama;

        $template->setValue('lama_perjalanan', $lama . ' (' . $lamaText . ') hari');
        $template->setValue('tanggal_berangkat', $start->translatedFormat('d F Y'));
        $template->setValue('tanggal_kembali', $end->translatedFormat('d F Y'));
        $template->setValue('mak', $sppd->mak ?? '-');

        $biaya = $sppd->biaya_transport ?? 0;
        $template->setValue('biaya_transport', 'Rp ' . number_format($biaya, 0, ',', '.') . ',-');
        $template->setValue('terbilang_biaya', ucfirst(trim(self::terbilang($biaya))) . ' rupiah');

        $template->setValue('nomor_surat_tugas', $record->nomor_surat);
        $template->setValue('tanggal_surat', Carbon::parse($record->tanggal)->translatedFormat('d F Y'));
        $template->setValue('tanggal_pernyataan', Carbon::parse($record->tanggal)->translatedFormat('d F Y'));

        $safeFilename = str_replace(['/', '\\'], '_', $sppd->nomor_sppd);
        $fileName = "SPPD_{$safeFilename}.docx";
        $tempPath = storage_path('app/temp_' . $fileName);
        $template->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend();
    }
}
