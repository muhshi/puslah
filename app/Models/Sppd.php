<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sppd extends Model
{
    protected $fillable = [
        'surat_tugas_id',
        'nomor_sppd',
        'nomor_urut_sppd',
        'kode_klasifikasi_sppd',
        'tingkat_perjalanan_dinas',
        'alat_angkutan',
        'mak',
        'ppk_name',
        'ppk_nip',
        'ppk_title',
        'maksud_perjalanan',
        'tempat_berangkat',
        'tempat_tujuan',
        'biaya_transport',
    ];

    public function suratTugas()
    {
        return $this->belongsTo(SuratTugas::class);
    }

    /**
     * Get skipped/unused nomor_urut_sppd for a given year.
     */
    public static function getSkippedNumbers(int $year): array
    {
        $usedNumbers = self::whereHas('suratTugas', function ($q) use ($year) {
            $q->whereYear('tanggal', $year);
        })
            ->pluck('nomor_urut_sppd')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($usedNumbers)) {
            return [];
        }

        $maxUsed = max($usedNumbers);
        $allNumbers = range(1, $maxUsed);

        return array_values(array_diff($allNumbers, $usedNumbers));
    }

    /**
     * Get skipped SPPD numbers grouped by Month name.
     * Logic: A missing number belongs to the month of the previous existing number.
     */
    public static function getSkippedNumbersByMonth(int $year): array
    {
        $existing = self::query()
            ->join('surat_tugas', 'sppds.surat_tugas_id', '=', 'surat_tugas.id')
            ->whereYear('surat_tugas.tanggal', $year)
            ->whereNotNull('sppds.nomor_urut_sppd')
            ->select('sppds.nomor_urut_sppd', 'surat_tugas.tanggal')
            ->orderBy('sppds.nomor_urut_sppd')
            ->get();

        if ($existing->isEmpty()) {
            return [];
        }

        $maxUrut = (int) $existing->last()->nomor_urut_sppd;
        $existingMap = $existing->pluck('tanggal', 'nomor_urut_sppd');

        $missingByMonth = [];
        $currentMonthName = 'Januari';

        for ($i = 1; $i <= $maxUrut; $i++) {
            if (isset($existingMap[$i])) {
                $date = \Carbon\Carbon::parse($existingMap[$i])->locale('id');
                $currentMonthName = $date->translatedFormat('F');
            } else {
                $missingByMonth[$currentMonthName][] = $i;
            }
        }

        foreach ($missingByMonth as $month => $numbers) {
            $missingByMonth[$month] = SuratTugas::formatSkippedNumbers($numbers);
        }

        return SuratTugas::sortMonthsChronologically($missingByMonth);
    }
}
