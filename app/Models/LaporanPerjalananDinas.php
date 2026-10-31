<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LaporanPerjalananDinas extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $table = 'laporan_perjalanan_dinas';

    protected $fillable = [
        'surat_tugas_id',
        'nomor_surat_tugas',
        'tujuan',
        'tanggal_kunjungan',
        'uraian_kegiatan',
        'nama_pejabat',
        'desa_pejabat',
    ];

    protected $casts = [
        'tanggal_kunjungan' => 'date',
    ];

    public function suratTugas(): BelongsTo
    {
        return $this->belongsTo(SuratTugas::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(LaporanFoto::class);
    }

    /**
     * Get all dates that already have an LPD for a given user.
     * Optionally exclude a specific LPD ID (useful when editing).
     *
     * @return array<string> List of Y-m-d date strings
     */
    public static function getExistingDatesForUser(int $userId, ?int $excludeId = null): array
    {
        return self::whereHas('suratTugas', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
        ->pluck('tanggal_kunjungan')
        ->map(function ($d) {
            if ($d instanceof \Carbon\CarbonInterface) {
                return $d->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($d)->format('Y-m-d');
        })
        ->unique()
        ->values()
        ->toArray();
    }

    /**
     * Check if a user already has an LPD on a specific date.
     * Returns the conflicting LaporanPerjalananDinas record or null.
     */
    public static function getDuplicateForUser(int $userId, string|\Carbon\CarbonInterface $date, ?int $excludeId = null): ?self
    {
        $dateStr = $date instanceof \Carbon\CarbonInterface ? $date->toDateString() : \Carbon\Carbon::parse($date)->toDateString();

        return self::with(['suratTugas.user'])
            ->whereHas('suratTugas', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereDate('tanggal_kunjungan', $dateStr)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * Get valid date range for an LPD belonging to a Surat Tugas.
     * Takes into account the Surat Tugas's dates (waktu_mulai / waktu_selesai or tanggal).
     * Only falls back to the Survey's dates if Surat Tugas has no date set.
     *
     * @return array{start: ?\Carbon\Carbon, end: ?\Carbon\Carbon}
     */
    public static function getDateRangeForSuratTugas(SuratTugas $st): array
    {
        $stStart = $st->waktu_mulai 
            ? \Carbon\Carbon::parse($st->waktu_mulai)->startOfDay() 
            : ($st->tanggal ? \Carbon\Carbon::parse($st->tanggal)->startOfDay() : null);
        $stEnd = $st->waktu_selesai 
            ? \Carbon\Carbon::parse($st->waktu_selesai)->startOfDay() 
            : $stStart;

        if ($stStart && $stEnd && $stEnd->lt($stStart)) {
            $stEnd = $stStart->copy();
        }

        if ($stStart) {
            return [
                'start' => $stStart,
                'end' => $stEnd ?? $stStart,
            ];
        }

        // Fallback to survey only if Surat Tugas has no dates
        $survey = $st->survey ?? ($st->survey_id ? \App\Models\Survey::find($st->survey_id) : null);
        $surveyStart = $survey?->start_date ? \Carbon\Carbon::parse($survey->start_date)->startOfDay() : null;
        $surveyEnd = $survey?->end_date ? \Carbon\Carbon::parse($survey->end_date)->startOfDay() : $surveyStart;

        if ($surveyStart && $surveyEnd && $surveyEnd->lt($surveyStart)) {
            $surveyEnd = $surveyStart->copy();
        }

        return [
            'start' => $surveyStart,
            'end' => $surveyEnd,
        ];
    }

    /**
     * Get maximum allowed LPDs for a given Surat Tugas based on its date range.
     * 1 day = 1 LPD.
     * Multi-day = number of days in the range.
     */
    public static function getMaxLpdCount(SuratTugas $st): int
    {
        $range = self::getDateRangeForSuratTugas($st);
        if (!$range['start'] || !$range['end']) {
            return 1;
        }

        return (int) $range['start']->diffInDays($range['end']) + 1;
    }

    /**
     * Check if a Surat Tugas can still receive another LPD.
     */
    public static function canCreateLpd(SuratTugas $st, ?int $excludeLpdId = null): bool
    {
        $max = self::getMaxLpdCount($st);
        $currentCount = ($st->relationLoaded('laporanPerjalananDinas') && $excludeLpdId === null)
            ? ($st->laporan_perjalanan_dinas_count ?? $st->laporanPerjalananDinas->count())
            : $st->laporanPerjalananDinas()
                ->when($excludeLpdId, fn ($q) => $q->where('id', '!=', $excludeLpdId))
                ->count();

        if ($currentCount >= $max) {
            return false;
        }

        return self::determineAvailableDate($st, $excludeLpdId) !== null;
    }

    /**
     * Determine the first available date for a SuratTugas that does not conflict with existing LPDs,
     * within the valid date range of the Surat Tugas.
     * Returns 'Y-m-d' or null if all dates in the range are already taken or quota reached.
     */
    public static function determineAvailableDate(SuratTugas $st, ?int $excludeLpdId = null): ?string
    {
        if (!$st->user_id) {
            return null;
        }

        $max = self::getMaxLpdCount($st);
        $currentCount = $st->laporanPerjalananDinas()
            ->when($excludeLpdId, fn ($q) => $q->where('id', '!=', $excludeLpdId))
            ->count();

        if ($currentCount >= $max) {
            return null;
        }

        $existingDates = self::getExistingDatesForUser($st->user_id, $excludeLpdId);
        $range = self::getDateRangeForSuratTugas($st);

        if (!$range['start'] || !$range['end']) {
            return null;
        }

        // Preferred starting date: waktu_mulai if available and within range, else range['start']
        $preferredStart = $st->waktu_mulai 
            ? \Carbon\Carbon::parse($st->waktu_mulai)->startOfDay() 
            : ($st->tanggal ? \Carbon\Carbon::parse($st->tanggal)->startOfDay() : $range['start']);

        if ($preferredStart->lt($range['start'])) {
            $preferredStart = $range['start']->copy();
        }
        if ($preferredStart->gt($range['end'])) {
            $preferredStart = $range['end']->copy();
        }

        // 1. Check from preferredStart forwards to range['end']
        $curr = $preferredStart->copy();
        while ($curr->lte($range['end'])) {
            $dateStr = $curr->format('Y-m-d');
            if (!in_array($dateStr, $existingDates, true)) {
                return $dateStr;
            }
            $curr->addDay();
        }

        // 2. If not found forwards, check backwards from preferredStart - 1 day to range['start']
        $curr = $preferredStart->copy()->subDay();
        while ($curr->gte($range['start'])) {
            $dateStr = $curr->format('Y-m-d');
            if (!in_array($dateStr, $existingDates, true)) {
                return $dateStr;
            }
            $curr->subDay();
        }

        return null;
    }
}
