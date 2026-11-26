<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use ZipArchive;

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
        'template_docx_path',
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

    protected static function booted()
    {
        static::saving(function (self $kop) {
            if ($kop->isDirty('template_docx_path') && ! empty($kop->template_docx_path)) {
                $kop->extractLogosFromDocx();
            }
        });
    }

    public function suratUndangans(): HasMany
    {
        return $this->hasMany(SuratUndangan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Ekstrak gambar logo header dari file template .docx yang diupload.
     */
    public function extractLogosFromDocx(): void
    {
        if (empty($this->template_docx_path)) {
            return;
        }

        $docxFullPath = Storage::disk('public')->exists($this->template_docx_path)
            ? Storage::disk('public')->path($this->template_docx_path)
            : (file_exists(public_path($this->template_docx_path)) ? public_path($this->template_docx_path) : null);

        if (! $docxFullPath || ! file_exists($docxFullPath)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxFullPath) !== true) {
            return;
        }

        $images = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (preg_match('/^word\/media\/.+\.(png|jpg|jpeg|gif)$/i', $entry)) {
                $content = $zip->getFromIndex($i);
                $info = @getimagesizefromstring($content);
                $images[] = [
                    'name' => basename($entry),
                    'ext' => strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
                    'width' => $info[0] ?? 0,
                    'content' => $content,
                ];
            }
        }
        $zip->close();

        if (empty($images)) {
            return;
        }

        // Urutkan berdasarkan lebar gambar: yang paling lebar adalah banner/logo BPS utama (kiri), sisanya logo kanan
        usort($images, fn ($a, $b) => $b['width'] <=> $a['width']);

        $slug = \Illuminate\Support\Str::slug($this->name ?: 'kop') . '_' . substr(md5($this->template_docx_path), 0, 8);
        $dir = "kop-surat/extracted/{$slug}";

        $left = $images[0];
        $leftPath = "{$dir}/logo_kiri.{$left['ext']}";
        Storage::disk('public')->put($leftPath, $left['content']);
        $this->logo_kiri_path = $leftPath;

        if (isset($images[1])) {
            $right = $images[1];
            $rightPath = "{$dir}/logo_kanan.{$right['ext']}";
            Storage::disk('public')->put($rightPath, $right['content']);
            $this->logo_kanan_path = $rightPath;
        } else {
            $this->logo_kanan_path = null;
        }
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
