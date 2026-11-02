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
