<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Survey extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_multiple' => 'boolean',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'survey_users')
            ->using(SurveyUser::class)
            ->withPivot(['status', 'registered_at', 'score', 'notes'])
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

    /**
     * Scope untuk survei yang memiliki aktivitas presensi dari anggotanya
     */
    public function scopeWithAttendanceActivity(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereExists(function ($sub) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
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
