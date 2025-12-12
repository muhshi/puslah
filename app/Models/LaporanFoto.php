<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanFoto extends Model
{
    use HasFactory;

    protected $table = 'laporan_foto';

    protected $fillable = [
        'laporan_perjalanan_dinas_id',
        'file_path',
        'keterangan',
        'urutan',
    ];

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjalananDinas::class, 'laporan_perjalanan_dinas_id');
    }
}
