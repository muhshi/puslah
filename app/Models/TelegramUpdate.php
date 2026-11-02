<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUpdate extends Model
{
    use HasFactory;

    protected $table = 'telegram_updates';

    protected $fillable = [
        'update_id',
        'chat_id',
        'message_id',
        'media_group_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
