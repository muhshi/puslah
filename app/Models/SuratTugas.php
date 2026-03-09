<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BlockedSuratTugasNumber;

class SuratTugas extends Model
{
    use HasFactory;

    protected $table = 'surat_tugas';

    protected $fillable = [
        'user_id',
        'survey_id',
        'nomor_surat',
        'nomor_urut',
        'kode_klasifikasi',
        'jabatan',
        'keperluan',
        'tempat_tugas',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'signer_name',
        'signer_nip',
        'signer_title',
        'signer_city',
        'signer_signature_path',
        'hash',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Survey::class);
    }

    /**
     * Get the user who created this surat tugas.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get skipped/unused nomor_urut for a given year (excludes blocked numbers)
     */
    public static function getSkippedNumbers(int $year): array
    {
        $usedNumbers = self::whereYear('tanggal', $year)
            ->pluck('nomor_urut')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        if (empty($usedNumbers)) {
            return [];
        }

        // Get blocked numbers for this year
        $blockedNumbers = BlockedSuratTugasNumber::getBlockedNumbers($year);

        // All occupied numbers = used + blocked
        $occupiedNumbers = array_unique(array_merge($usedNumbers, $blockedNumbers));
        sort($occupiedNumbers);

        $maxUsed = max($usedNumbers);
        $allNumbers = range(1, $maxUsed);

        // Skipped = numbers not in used AND not in blocked (truly missing)
        return array_values(array_diff($allNumbers, $occupiedNumbers));
    }

    /**
     * Format skipped numbers as readable string (e.g., "5, 8-10, 23")
     */
    public static function formatSkippedNumbers(array $numbers): string
    {
        if (empty($numbers)) {
            return '';
        }

        $ranges = [];
        $start = $numbers[0];
        $end = $numbers[0];

        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] == $end + 1) {
                $end = $numbers[$i];
            } else {
                $ranges[] = $start == $end ? (string) $start : "{$start}-{$end}";
                $start = $numbers[$i];
                $end = $numbers[$i];
            }
        }
        $ranges[] = $start == $end ? (string) $start : "{$start}-{$end}";

        return implode(', ', $ranges);
    }

    /**
     * Get next available nomor_urut for a given year (skips blocked numbers)
     */
    public static function getNextNomorUrut(int $year): int
    {
        $maxUsed = self::whereYear('tanggal', $year)->max('nomor_urut') ?? 0;
        $maxBlocked = BlockedSuratTugasNumber::where('year', $year)->max('nomor_urut') ?? 0;
        $next = max($maxUsed, $maxBlocked) + 1;

        return $next;
    }

    /**
     * Get all occupied nomor_urut (used + blocked) for a given year, as a flipped array for fast lookup
     */
    public static function getOccupiedNumbers(int $year): array
    {
        $usedNumbers = self::whereYear('tanggal', $year)
            ->pluck('nomor_urut')
            ->toArray();

        $blockedNumbers = BlockedSuratTugasNumber::getBlockedNumbers($year);

        $all = array_unique(array_merge($usedNumbers, $blockedNumbers));

        return array_flip($all);
    }

    /**
     * Get skipped numbers grouped by Month name.
     * Logic: A missing number belongs to the month of the *previous* existing number.
     * If missing at start, defaults to January.
     */
    public static function getSkippedNumbersByMonth(int $year): array
    {
        // Get all existing (nomor_urut, month) pairs for the year
        // We select tanggal to know the month
        $existing = self::whereYear('tanggal', $year)
            ->select('nomor_urut', 'tanggal')
            ->orderBy('nomor_urut')
            ->get();

        if ($existing->isEmpty()) {
            return [];
        }

        $maxUrut = $existing->last()->nomor_urut;
        $existingMap = $existing->pluck('tanggal', 'nomor_urut'); // [urut => '2023-01-30', ...]

        $missingByMonth = [];
        $currentMonthName = 'Januari'; // Default start

        for ($i = 1; $i <= $maxUrut; $i++) {
            if (isset($existingMap[$i])) {
                // Number exists, update current reference month
                $date = \Carbon\Carbon::parse($existingMap[$i])->locale('id');
                $currentMonthName = $date->translatedFormat('F');
            } else {
                // Number missing, assign to current reference month
                $missingByMonth[$currentMonthName][] = $i;
            }
        }

        // Format the arrays to be readable ranges
        foreach ($missingByMonth as $month => $numbers) {
            $missingByMonth[$month] = self::formatSkippedNumbers($numbers);
        }

        return $missingByMonth;
    }
}
