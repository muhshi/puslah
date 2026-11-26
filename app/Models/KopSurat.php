<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class KopSurat extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $table = 'kop_surats';

    protected $fillable = [
        'name',
        'logo_kiri_path',
        'logo_kanan_path',
        'instansi_teks',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function suratUndangans(): HasMany
    {
        return $this->hasMany(SuratUndangan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Dapatkan kop surat default (Kop BerAKHLAK atau Kop SE2026).
     */
    public static function getDefaultKop(): ?self
    {
        $default = self::where('is_default', true)->where('is_active', true)->first();
        if ($default) {
            return $default;
        }

        $berakhlak = self::where('name', 'like', '%BerAKHLAK%')->where('is_active', true)->first();
        if ($berakhlak) {
            return $berakhlak;
        }

        return self::where('is_active', true)->first();
    }
}
