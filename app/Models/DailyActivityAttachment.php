<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DailyActivityAttachment extends Model
{
    use HasFactory;

    protected $table = 'daily_activity_attachments';

    protected $fillable = [
        'daily_activity_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'is_stamped',
        'latitude',
        'longitude',
        'captured_at',
        'caption',
        'sort_order',
    ];

    protected $casts = [
        'is_stamped' => 'boolean',
        'captured_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function dailyActivity(): BelongsTo
    {
        return $this->belongsTo(DailyActivity::class, 'daily_activity_id');
    }

    /**
     * Terapkan watermark stempel koordinat dan waktu permanen ke file fisik foto.
     */
    public function applyWatermark(): bool
    {
        if (empty($this->file_path)) {
            return false;
        }

        $fullPath = storage_path('app/public/' . $this->file_path);
        if (!file_exists($fullPath)) {
            return false;
        }

        $caption = $this->caption;
        if (empty($caption) && $this->dailyActivity) {
            $caption = $this->dailyActivity->raw_content;
        }

        $success = app(\App\Services\PhotoWatermarkService::class)->applyWatermark($fullPath, [
            'latitude' => $this->latitude ?: -6.894561,
            'longitude' => $this->longitude ?: 110.637492,
            'captured_at' => $this->captured_at ?: now(),
            'caption' => $caption ?: 'Dokumentasi Kegiatan BPS',
            'office_name' => 'BPS KABUPATEN DEMAK',
        ]);

        if ($success) {
            clearstatcache(true, $fullPath);
            $this->updateQuietly([
                'is_stamped' => true,
                'file_size' => filesize($fullPath),
            ]);
        }

        return $success;
    }

    public function getUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
