<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Get skipped/unused nomor_urut for a given year
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

        $allNumbers = range(1, max($usedNumbers));
        return array_values(array_diff($allNumbers, $usedNumbers));
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
     * Get next available nomor_urut for a given year
     */
    public static function getNextNomorUrut(int $year): int
    {
        return (self::whereYear('tanggal', $year)->max('nomor_urut') ?? 0) + 1;
    }
}
