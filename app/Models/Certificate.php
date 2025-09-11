<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'user_id',
        'certificate_no',
        'issued_at',
        'file_path',
        'hash',
        'revoked',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked' => 'bool',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
