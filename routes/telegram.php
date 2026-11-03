<?php

use App\Telegram\Handlers\DailyActivityTelegramHandler;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\MessageType;

/** @var SergiX44\Nutgram\Nutgram $bot */

$handler = app(DailyActivityTelegramHandler::class);

// Command /start dan /start {token}
$bot->onCommand('start {token}', [$handler, 'handleStart'])
    ->description('Mulai bot atau hubungkan dengan akun Puslah');

$bot->onCommand('start', [$handler, 'handleStart'])
    ->description('Mulai bot Bukti Dukung Puslah');

// Command /today
$bot->onCommand('today', [$handler, 'handleToday'])
    ->description('Lihat daftar kegiatan dan bukti dukung hari ini');

// Command /help
$bot->onCommand('help', [$handler, 'handleHelp'])
    ->description('Panduan interaktif penggunaan bot');

// Handling pesan teks
$bot->onMessageType(MessageType::TEXT, [$handler, 'handleTextMessage']);

// Handling pesan foto
$bot->onMessageType(MessageType::PHOTO, [$handler, 'handlePhotoMessage']);

// Handling callback query (AI Polish)
$bot->onCallbackQueryData('ai_polish:{id}', [$handler, 'handleAiPolishCallback']);
