<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSuratTugasNumber extends Model
{
    protected $table = 'blocked_surat_tugas_numbers';

    protected $fillable = [
        'nomor_urut',
        'year',
        'keterangan',
        'blocked_by',
    ];

    protected $casts = [
        'nomor_urut' => 'integer',
        'year' => 'integer',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Get all blocked nomor_urut for a given year
     */
    public static function getBlockedNumbers(int $year): array
    {
        return self::where('year', $year)
            ->pluck('nomor_urut')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get blocked numbers grouped by keterangan 
     */
    public static function getBlockedNumbersGroupedByKeterangan(int $year): array
    {
        $records = self::where('year', $year)
            ->orderBy('nomor_urut')
            ->get();
            
        $grouped = [];
        foreach ($records as $record) {
            $ket = $record->keterangan ?? 'Tanpa Keterangan';
            $grouped[$ket][] = $record->nomor_urut;
        }

        $formatted = [];
        foreach ($grouped as $ket => $numbers) {
            $formatted[$ket] = SuratTugas::formatSkippedNumbers($numbers);
        }

        return $formatted;
    }

    /**
     * Check if a specific nomor_urut is blocked for a given year
     */
    public static function isBlocked(int $nomorUrut, int $year): bool
    {
        return self::where('nomor_urut', $nomorUrut)
            ->where('year', $year)
            ->exists();
    }

    /**
     * Block a range of nomor_urut for a given year
     */
    public static function blockRange(int $from, int $to, int $year, ?string $keterangan = null, ?int $blockedBy = null): int
    {
        $count = 0;
        $blockedBy = $blockedBy ?? auth()->id();

        for ($i = $from; $i <= $to; $i++) {
            // Skip if already blocked or already used by a surat tugas
            $alreadyBlocked = self::where('nomor_urut', $i)->where('year', $year)->exists();
            $alreadyUsed = SuratTugas::whereYear('tanggal', $year)->where('nomor_urut', $i)->exists();

            if (!$alreadyBlocked && !$alreadyUsed) {
                self::create([
                    'nomor_urut' => $i,
                    'year' => $year,
                    'keterangan' => $keterangan,
                    'blocked_by' => $blockedBy,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
