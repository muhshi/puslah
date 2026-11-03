<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DailyActivity extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $table = 'daily_activities';

    protected $fillable = [
        'user_id',
        'surat_tugas_id',
        'shared_work_report_id',
        'activity_date',
        'started_at',
        'ended_at',
        'raw_content',
        'ai_formal_content',
        'final_content',
        'status',
        'source',
        'laporan_perjalanan_dinas_id',
    ];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedWorkReport(): BelongsTo
    {
        return $this->belongsTo(SharedWorkReport::class, 'shared_work_report_id');
    }

    public function sharedWorkReports(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SharedWorkReport::class, 'daily_activity_shared_work_report');
    }

    public function suratTugas(): BelongsTo
    {
        return $this->belongsTo(SuratTugas::class);
    }

    public function laporanPerjalananDinas(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjalananDinas::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DailyActivityAttachment::class, 'daily_activity_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('activity_date', [$startDate, $endDate]);
    }

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('activity_date', $month)->whereYear('activity_date', $year);
    }
}
