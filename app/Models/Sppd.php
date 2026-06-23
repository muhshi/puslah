<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sppd extends Model
{
    protected $fillable = [
        'surat_tugas_id',
        'nomor_sppd',
        'nomor_urut_sppd',
        'kode_klasifikasi_sppd',
        'tingkat_perjalanan_dinas',
        'alat_angkutan',
        'mak',
        'ppk_name',
        'ppk_nip',
        'ppk_title',
        'maksud_perjalanan',
        'tempat_berangkat',
        'tempat_tujuan',
        'biaya_transport',
    ];

    public function suratTugas()
    {
        return $this->belongsTo(SuratTugas::class);
    }
}
