<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wfa extends Model
{

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // scope: aktif di tanggal tertentu
    public function scopeActiveOn(Builder $q, $date): Builder
    {
        return $q->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    // helper: apakah aktif hari ini (WIB)
    public function isActiveToday(): bool
    {
        $today = now('Asia/Jakarta')->toDateString();
        return $this->start_date->toDateString() <= $today
            && $this->end_date->toDateString() >= $today;
    }
}
