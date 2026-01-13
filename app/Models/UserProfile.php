<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'avatar_path',
        'full_name',
        'nickname',
        'birth_place',
        'birth_date',
        'gender',              // 'L' / 'P' atau null
        'address',
        'phone',
        'employment_status',   // 'aktif' / 'nonaktif'
        'jabatan',             // New Jabatan column
        'nip',                 // NIP for Pegawai BPS
        'old_nip',             // NIP Lama for Pegawai BPS
        'pangkat_golongan',    // Pangkat/Golongan for Pegawai BPS
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
