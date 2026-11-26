<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SurveyUser extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'survey_users';

    protected $fillable = [
        'survey_id',
        'user_id',
        'survey_class_id',
        'role',
        'registered_at',
        'status',
        'score',
        'notes',
        'role_pelatihan',
        'kelas',
        'asal',
        'tujuan',
        'transport_override',
        'uang_harian_override',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'transport_override' => 'integer',
        'uang_harian_override' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (self $surveyUser) {
            // Sinkronisasi kelas (survey_class_id <-> kelas)
            if ($surveyUser->isDirty('survey_class_id') && $surveyUser->survey_class_id) {
                $className = SurveyClass::find($surveyUser->survey_class_id)?->name;
                if ($className) {
                    $surveyUser->kelas = $className;
                }
            } elseif (empty($surveyUser->survey_class_id) && !empty($surveyUser->kelas) && $surveyUser->survey_id) {
                $class = SurveyClass::where('survey_id', $surveyUser->survey_id)
                    ->where('name', $surveyUser->kelas)
                    ->first();
                if ($class) {
                    $surveyUser->survey_class_id = $class->id;
                }
            }

            // Sinkronisasi role (role <-> role_pelatihan)
            if ($surveyUser->isDirty('role') && !empty($surveyUser->role)) {
                $surveyUser->role_pelatihan = $surveyUser->role;
            } elseif ($surveyUser->isDirty('role_pelatihan') && !empty($surveyUser->role_pelatihan) && empty($surveyUser->role)) {
                $surveyUser->role = $surveyUser->role_pelatihan;
            } elseif (empty($surveyUser->role) && !empty($surveyUser->role_pelatihan)) {
                $surveyUser->role = $surveyUser->role_pelatihan;
            }
        });
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function surveyClass()
    {
        return $this->belongsTo(SurveyClass::class, 'survey_class_id');
    }

    public function class()
    {
        return $this->belongsTo(SurveyClass::class, 'survey_class_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'user_id', 'user_id')
            ->where('survey_id', $this->survey_id);
    }
}
