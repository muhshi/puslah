<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratUndanganSchedule extends Model
{
    use HasFactory;

    protected $table = 'surat_undangan_schedules';

    protected $fillable = [
        'surat_undangan_id',
        'hari',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'materi',
        'jp',
        'metode',
        'keterangan',
        'urutan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'durasi_menit' => 'integer',
        'jp' => 'integer',
        'urutan' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function ($schedule) {
            if ($schedule->waktu_mulai && $schedule->waktu_selesai) {
                $schedule->durasi_menit = self::calculateDurationMinutes($schedule->waktu_mulai, $schedule->waktu_selesai);
            }
        });
    }

    public function undangan(): BelongsTo
    {
        return $this->belongsTo(SuratUndangan::class, 'surat_undangan_id');
    }

    /**
     * Hitung durasi waktu dalam menit dari dua string waktu ("07.30", "08.00").
     */
    public static function calculateDurationMinutes(string $start, string $end): ?int
    {
        try {
            $startNorm = str_replace('.', ':', trim($start));
            $endNorm = str_replace('.', ':', trim($end));

            $startTime = Carbon::createFromFormat('H:i', $startNorm);
            $endTime = Carbon::createFromFormat('H:i', $endNorm);

            return (int) $startTime->diffInMinutes($endTime);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format durasi waktu menjadi string "0:30" atau "1:30".
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->durasi_menit === null || $this->durasi_menit <= 0) {
            return '-';
        }

        $hours = floor($this->durasi_menit / 60);
        $minutes = $this->durasi_menit % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }
}
