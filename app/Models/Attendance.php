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
        $mins = $this->workedMinutes();
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return "{$h} jam {$m} menit";
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

    public function workedMinutes(): int
    {
        if (empty($this->start_time) || empty($this->end_time)) {
            return 0; // belum checkout
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // kalau end < start (shift malam), anggap selesai keesokan hari
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $end->diffInMinutes($start);
    }

    /** Merah kalau < 7 jam */
    public function underSevenHours(): bool
    {
        return $this->workedMinutes() < (7 * 60); // 420 menit
    }
}
