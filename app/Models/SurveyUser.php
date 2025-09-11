<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyUser extends Model
{
    use HasFactory;

    protected $table = 'survey_users';

    protected $fillable = [
        'survey_id',
        'user_id',
        'registered_at',
        'status',
        'score',
        'notes',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
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
