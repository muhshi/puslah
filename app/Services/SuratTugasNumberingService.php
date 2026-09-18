<?php

namespace App\Services;

use App\Models\SuratTugas;
use App\Settings\SystemSettings;
use Carbon\Carbon;

class SuratTugasNumberingService
{
    /**
     * Format string nomor Surat Tugas resmi.
     */
    public static function formatNomorSurat(int|string $nomorUrut, ?string $klasifikasi = 'KP.650', ?int $year = null): string
    {
        $settings = app(SystemSettings::class);
        $prefix = $settings->surat_prefix ?? 'B';
        $office = $settings->office_code ?? '33210';
        $urut = str_pad((string) $nomorUrut, 4, '0', STR_PAD_LEFT);
        $klasifikasi = $klasifikasi ?: 'KP.650';
        $year = $year ?: now()->year;

        return "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";
    }

    /**
     * Format string nomor SPPD resmi.
     */
    public static function formatNomorSppd(int|string $nomorUrut, ?string $klasifikasi = 'KP.650', ?int $year = null): string
    {
        return SppdService::formatNomorSppd($nomorUrut, $klasifikasi, $year);
    }

    /**
     * Format rentang tanggal penugasan.
     */
    public static function formatPeriodeTugas(mixed $mulai, mixed $selesai): string
    {
        return SuratTugasPdfService::formatPeriodeTugas($mulai, $selesai);
    }

    /**
     * Dapatkan nomor urut Surat Tugas berikutnya untuk tahun tertentu.
     */
    public static function getNextNomorUrut(?int $year = null): int
    {
        return SuratTugas::getNextNomorUrut($year ?: now()->year);
    }

    /**
     * Dapatkan nomor urut SPPD berikutnya untuk tahun tertentu.
     */
    public static function getNextNomorUrutSppd(?int $year = null): int
    {
        return SuratTugas::getNextNomorUrutSppd($year ?: now()->year);
    }

    /**
     * Format list nomor terlewat/loncat menjadi string range (misal: 5-8, 12).
     */
    public static function formatSkippedNumbers(array $numbers): string
    {
        return SuratTugas::formatSkippedNumbers($numbers);
    }
}
