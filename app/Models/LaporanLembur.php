<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LaporanLembur extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected $fillable = [
        'user_id',
        'waktu',
        'mulai',
        'selesai',
        'pekerjaan',
        'foto_1',
        'foto_2',
        'foto_3',
        'foto_4',
        'status',
    ];

    protected $casts = [
        'waktu' => 'date',
        'mulai' => 'datetime',
        'selesai' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
