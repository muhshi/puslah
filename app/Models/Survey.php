<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Survey extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var array<string, string> */
    public const DEFAULT_PELATIHAN_DIPA = [
        'program' => 'PROGRAM PENYEDIAAN DAN PELAYANAN INFORMASI STATISTIK (054.01.GG)',
        'kegiatan_dipa' => 'PENYEDIAAN DAN PENGEMBANGAN STATISTIK HARGA (2903)',
        'klasifikasi_ro' => 'DATA DAN INFORMASI PUBLIK (2903.BMA)',
        'rincian_output' => 'PUBLIKASI/LAPORAN SPKRT (2903.BMA.007)',
        'komponen' => 'PERSIAPAN (2903.BMA.009.051A)',
        'mak' => 'BELANJA PERJALANAN DINAS DALAM KOTA (524113)',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $fillable = [
        'name',
        'description',
        'dasar_surat',
        'start_date',
        'end_date',
        'is_active',
        'is_multiple',
        'is_pelatihan',
        'lokasi',
        'kota_kegiatan',
        'nomor_surat_undangan',
        'tanggal_surat_undangan',
        'rujukan_surat',
        'link_materi',
        'program',
        'kegiatan_dipa',
        'klasifikasi_ro',
        'rincian_output',
        'komponen',
        'mak',
        'biaya_transport',
        'biaya_uang_harian',
        'penyelenggara_user_id',
        'ppk_user_id',
        'bendahara_user_id',
        'include_atk',
        'atk_items',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_multiple' => 'boolean',
        'is_pelatihan' => 'boolean',
        'tanggal_surat_undangan' => 'date',
        'biaya_transport' => 'integer',
        'biaya_uang_harian' => 'integer',
        'include_atk' => 'boolean',
        'atk_items' => 'array',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'survey_users')
            ->using(SurveyUser::class)
            ->withPivot([
                'status',
                'registered_at',
                'score',
                'notes',
                'role_pelatihan',
                'kelas',
                'asal',
                'tujuan',
                'transport_override',
                'uang_harian_override',
            ])
            ->withTimestamps();
    }

    public function suratTugas(): HasMany
    {
        return $this->hasMany(SuratTugas::class);
    }

    public function surveyUsers(): HasMany
    {
        return $this->hasMany(SurveyUser::class);
    }

    public function penyelenggara(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyelenggara_user_id');
    }

    public function ppk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppk_user_id');
    }

    public function bendahara(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bendahara_user_id');
    }

    public function scopePelatihan(Builder $query): Builder
    {
        return $query->where('is_pelatihan', true);
    }

    public function scopeNonPelatihan(Builder $query): Builder
    {
        return $query->where('is_pelatihan', false);
    }

    /**
     * Label tampil pada dropdown agar Pelatihan dan Kegiatan mudah dibedakan.
     */
    public function getSelectorLabelAttribute(): string
    {
        $prefix = $this->is_pelatihan ? '[Pelatihan]' : '[Kegiatan]';

        return "{$prefix} {$this->name}";
    }

    /**
     * Scope untuk survei yang memiliki aktivitas presensi dari anggotanya
     */
    public function scopeWithAttendanceActivity(Builder $query): Builder
    {
        return $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('survey_users')
                ->join('attendances', 'attendances.user_id', '=', 'survey_users.user_id')
                ->whereColumn('survey_users.survey_id', 'surveys.id')
                ->where(function ($q) {
                    $q->whereNull('surveys.start_date')
                        ->orWhereRaw('DATE(attendances.created_at) >= DATE(surveys.start_date)');
                })
                ->where(function ($q) {
                    $q->whereNull('surveys.end_date')
                        ->orWhereRaw('DATE(attendances.created_at) <= DATE(surveys.end_date)');
                });
        });
    }
}
