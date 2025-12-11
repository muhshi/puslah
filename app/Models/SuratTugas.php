<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratTugas extends Model
{
    use HasFactory;

    protected $table = 'surat_tugas';

    protected $fillable = [
        'user_id',
        'nomor_surat',
        'nomor_urut',
        'kode_klasifikasi',
        'jabatan',
        'keperluan',
        'dasar_surat',
        'tempat_tugas',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'signer_name',
        'signer_nip',
        'signer_title',
        'signer_city',
        'signer_signature_path',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
