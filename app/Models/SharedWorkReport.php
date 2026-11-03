<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SharedWorkReport extends Model
{
    use HasFactory;

    protected $table = 'shared_work_reports';

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'activity_ids',
        'surat_tugas_id',
        'is_active',
        'view_count',
    ];

    protected $casts = [
        'activity_ids' => 'array',
        'is_active' => 'boolean',
        'view_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suratTugas(): BelongsTo
    {
        return $this->belongsTo(SuratTugas::class, 'surat_tugas_id');
    }

    /**
     * Ambil koleksi model DailyActivity yang dibundel, terurut kronologis tanggal kegiatan.
     */
    public function getActivities(): Collection
    {
        $ids = is_array($this->activity_ids) ? $this->activity_ids : [];
        if (empty($ids)) {
            return collect();
        }

        return DailyActivity::with(['attachments', 'suratTugas'])
            ->whereIn('id', $ids)
            ->orderBy('activity_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Dapatkan URL publik resmi laporan bukti dukung.
     */
    public function getPublicUrlAttribute(): string
    {
        return route('shared-work-report.show', ['uuid' => $this->uuid]);
    }

    /**
     * Dapatkan URL unduh dokumen Word resmi.
     */
    public function getDownloadWordUrlAttribute(): string
    {
        return route('shared-work-report.download', ['uuid' => $this->uuid]);
    }
}
