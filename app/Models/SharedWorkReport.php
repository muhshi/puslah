<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'tag_slug',
        'description',
        'period_start',
        'period_end',
        'activity_ids',
        'surat_tugas_id',
        'is_active',
        'view_count',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
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
            if (!empty($model->tag_slug)) {
                $model->tag_slug = Str::slug(ltrim($model->tag_slug, '#'));
            }
        });

        static::updating(function ($model) {
            if (!empty($model->tag_slug)) {
                $model->tag_slug = Str::slug(ltrim($model->tag_slug, '#'));
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

    public function dailyActivities(): HasMany
    {
        return $this->hasMany(DailyActivity::class, 'shared_work_report_id');
    }

    /**
     * Ambil koleksi model DailyActivity yang dibundel, terurut kronologis tanggal kegiatan.
     * Menggabungkan kegiatan yang ditautkan langsung, melalui activity_ids, maupun rentang tanggal.
     */
    public function getActivities(): Collection
    {
        $explicitIds = is_array($this->activity_ids) ? $this->activity_ids : [];

        $query = DailyActivity::with(['attachments', 'suratTugas'])
            ->where(function ($q) use ($explicitIds) {
                // 1. Yang ditautkan langsung via shared_work_report_id
                $q->where('shared_work_report_id', $this->id);

                // 2. Yang ada di daftar ID eksplisit
                if (!empty($explicitIds)) {
                    $q->orWhereIn('id', $explicitIds);
                }

                // 3. Jika diset rentang tanggal & milik user yang sama
                if ($this->period_start && $this->period_end) {
                    $q->orWhere(function ($sub) {
                        $sub->where('user_id', $this->user_id)
                            ->whereBetween('activity_date', [$this->period_start, $this->period_end]);
                    });
                }
            })
            ->orderBy('activity_date', 'asc')
            ->orderBy('id', 'asc');

        return $query->get();
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
