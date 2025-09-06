<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_latitude',
        'schedule_longitude',
        'schedule_start_time',
        'schedule_end_time',
        'start_latitude',
        'end_latitude',
        'start_longitude',
        'end_longitude',
        'start_time',
        'end_time',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLate()
    {
        $scheduleStartTime = Carbon::parse($this->schedule_start_time);
        $startTime = Carbon::parse($this->start_time);

        return $startTime->greaterThan($scheduleStartTime);
    }

    public function workDuration(): string
    {
        $m = $this->durationMinutes();
        $h = intdiv($m, 60);
        $mm = $m % 60;

        return "{$h} jam {$mm} menit";
    }

    public function lessWorkDuration()
    {
        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);

        $duration = $startTime->diff($endTime);

        $hours = $duration->format('%h');

        return $hours >= 7 ? true : false;
    }

    public function hasWorked(): bool
    {
        return !empty($this->end_time);
    }

    public function isCheckedOut(): bool
    {
        return !empty($this->end_time);
    }

    public function durationMinutes(): int
    {
        // Belum checkout
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        // Parse aman untuk 'H:i' / 'H:i:s'
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Jika end < start, anggap shift nyebrang hari
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end); // SELALU positif
    }

    /** Merah kalau < 7 jam */
    public function underSevenHours(): bool
    {
        return $this->durationMinutes() < (7 * 60); // 420 menit
    }
}
