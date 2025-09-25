<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'background_path',
        'paper',
        'orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'qr_left',
        'qr_top',
        'qr_size',
        'signer_name',
        'signer_title',
        'signer_image_path',
        'city_label',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'margin_top' => 'int',
        'margin_right' => 'int',
        'margin_bottom' => 'int',
        'margin_left' => 'int',
        'qr_left' => 'int',
        'qr_top' => 'int',
        'qr_size' => 'int',
    ];

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }

    public function getSignerImageUrlAttribute(): ?string
    {
        return $this->signer_image_path ? Storage::disk('public')->url($this->signer_image_path) : null;
    }
}
