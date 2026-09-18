<?php

namespace App\Services;

use App\Models\SuratTugas;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuratTugasPdfService
{
    /**
     * Format rentang tanggal pelaksanaan tugas secara rapi dalam Bahasa Indonesia.
     */
    public static function formatPeriodeTugas(mixed $mulai, mixed $selesai): string
    {
        if (!$mulai || !$selesai) {
            return '-';
        }

        $startDate = Carbon::parse($mulai);
        $endDate = Carbon::parse($selesai);

        // Kasus 1: Hari yang sama
        if ($startDate->isSameDay($endDate)) {
            return $startDate->translatedFormat('d F Y');
        }

        // Kasus 2: Bulan dan tahun yang sama
        if ($startDate->month === $endDate->month && $startDate->year === $endDate->year) {
            return $startDate->translatedFormat('d') . ' - ' . $endDate->translatedFormat('d F Y');
        }

        // Kasus 3: Tahun sama, bulan berbeda
        if ($startDate->year === $endDate->year) {
            return $startDate->translatedFormat('d F') . ' - ' . $endDate->translatedFormat('d F Y');
        }

        // Kasus 4: Tahun berbeda
        return $startDate->translatedFormat('d F Y') . ' - ' . $endDate->translatedFormat('d F Y');
    }

    /**
     * Render PDF Surat Tugas dan set enkripsi jika password master dikonfigurasi.
     */
    public function renderPdf(SuratTugas $record, bool $isPreview = false): DomPdfInstance
    {
        // 1. Pastikan hash verifikasi ada
        if (!$record->hash) {
            $record->update(['hash' => Str::random(32)]);
        }

        // 2. Load Logo Base64 (Cache 24 jam)
        $logoBase64 = Cache::remember('logo_bps_static_base64', 86400, function () {
            $logoPath = public_path('images/logo_bps.png');
            if (file_exists($logoPath)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
            return null;
        });

        // 3. Generate QR (hanya jika approved)
        $qrBase64 = null;
        if ($record->status === 'approved') {
            $verifyUrl = route('surat-tugas.verify', $record->hash);
            $qrSvg = QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl);
            $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        }

        // 4. Format tanggal periode tugas
        $periode = self::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);

        // 5. Generate PDF dengan DomPDF
        /** @var DomPdfInstance $pdf */
        $pdf = Pdf::loadView('surat-tugas.pdf_table_layout', [
            'surat' => $record,
            'logoBase64' => $logoBase64,
            'qrBase64' => $qrBase64,
            'periode' => $periode,
            'is_preview' => $isPreview,
        ])->setPaper('a4', 'portrait');

        // 6. Proteksi / Enkripsi jika Master Password diatur
        $settings = app(SystemSettings::class);
        if (!empty($settings->pdf_master_password)) {
            $pdf->setEncryption('', $settings->pdf_master_password, ['print']);
        }

        return $pdf;
    }

    /**
     * Generate nama file PDF yang ramah sistem operasi.
     */
    public function getFileName(SuratTugas $record): string
    {
        $surveyName = $record->survey ? str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->survey->name) : 'NoSurvey';
        $userName = str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->user->name ?? 'User');
        $nomorSurat = str_replace(['/', '\\'], '_', $record->nomor_surat ?? 'ST');

        return "{$nomorSurat}-{$surveyName}-{$userName}.pdf";
    }

    /**
     * Stream download PDF Surat Tugas langsung ke browser.
     */
    public function downloadPdf(SuratTugas $record): StreamedResponse
    {
        $pdf = $this->renderPdf($record);
        $fileName = $this->getFileName($record);

        return response()->streamDownload(fn() => print ($pdf->output()), $fileName);
    }
}
