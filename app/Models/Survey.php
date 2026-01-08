<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'dasar_surat',
        'start_date',
        'end_date',
        'is_active',
        'complete_rule',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
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
}
