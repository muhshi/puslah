<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SuratUndangan extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $table = 'surat_undangans';

    protected $fillable = [
        'survey_id',
        'kop_surat_id',
        'nomor_surat',
        'sifat',
        'lampiran_teks',
        'hal',
        'tanggal_surat',
        'yth_teks',
        'paragraf_pembuka',
        'tipe_isi',
        'isi_surat_html',
        'sertakan_lampiran_peserta',
        'kolom_lampiran_peserta',
        'urutan_role_peserta',
        'peserta_ids',
        'sertakan_lampiran_jadwal',
        'mode_jadwal',
        'jadwal_image_path',
        'tampilkan_durasi_jadwal',
        'signer_name',
        'signer_nip',
        'signer_title',
        'signer_city',
        'signer_signature_path',
        'status',
        'hash',
        'created_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'sertakan_lampiran_peserta' => 'boolean',
        'sertakan_lampiran_jadwal' => 'boolean',
        'tampilkan_durasi_jadwal' => 'boolean',
        'kolom_lampiran_peserta' => 'array',
        'urutan_role_peserta' => 'array',
        'peserta_ids' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($undangan) {
            if (empty($undangan->hash)) {
                $undangan->hash = Str::random(32);
            }
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function kopSurat(): BelongsTo
    {
        return $this->belongsTo(KopSurat::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SuratUndanganSchedule::class)->orderBy('urutan')->orderBy('tanggal')->orderBy('waktu_mulai');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedTanggalAttribute(): string
    {
        if (!$this->tanggal_surat) {
            return '-';
        }

        return \Carbon\Carbon::parse($this->tanggal_surat)->locale('id')->translatedFormat('d F Y');
    }

    /**
     * Dapatkan nama penandatangan default:
     * Mengutamakan user dengan role 'Kepala', fallback ke setting cert_signer_name, lalu 'Kepala BPS Demak'.
     */
    public static function getDefaultSigner(): array
    {
        $kepala = User::whereHas('roles', fn ($q) => $q->where('name', 'Kepala'))->first();
        if ($kepala) {
            $profile = $kepala->profile;
            return [
                'name'  => $profile?->full_name ?: $kepala->name,
                'nip'   => $profile?->nip ?: ($profile?->old_nip ?: '-'),
                'title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
                'city'  => 'Demak',
            ];
        }

        try {
            $settings = app(\App\Settings\SystemSettings::class);
            return [
                'name'  => !empty($settings->cert_signer_name) && $settings->cert_signer_name !== '-' ? $settings->cert_signer_name : 'Khomarudin',
                'nip'   => !empty($settings->cert_signer_nip) ? $settings->cert_signer_nip : '-',
                'title' => !empty($settings->cert_signer_title) ? $settings->cert_signer_title : 'Kepala Badan Pusat Statistik Kabupaten Demak',
                'city'  => !empty($settings->cert_city) ? $settings->cert_city : 'Demak',
            ];
        } catch (\Throwable $e) {
            return [
                'name'  => 'Khomarudin',
                'nip'   => '-',
                'title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
                'city'  => 'Demak',
            ];
        }
    }
}
