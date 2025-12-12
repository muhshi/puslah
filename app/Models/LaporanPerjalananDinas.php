<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanPerjalananDinas extends Model
{
    use HasFactory;

    protected $table = 'laporan_perjalanan_dinas';

    protected $fillable = [
        'surat_tugas_id',
        'nomor_surat_tugas',
        'tujuan',
        'tanggal_kunjungan',
        'uraian_kegiatan',
        'nama_pejabat',
        'desa_pejabat',
    ];

    protected $casts = [
        'tanggal_kunjungan' => 'date',
    ];

    public function suratTugas(): BelongsTo
    {
        return $this->belongsTo(SuratTugas::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(LaporanFoto::class);
    }
}
