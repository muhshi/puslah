<?php

namespace App\Telegram\Handlers;

use App\Models\DailyActivity;
use App\Models\DailyActivityAttachment;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\DailyActivityAiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DailyActivityTelegramHandler
{
    /**
     * Handle command /start atau /start {token}
     */
    public function handleStart(Nutgram $bot, ?string $token = null): void
    {
        $chatId = $bot->chatId();

        // 1. Jika ada token, coba hubungkan akun
        if (!empty($token)) {
            $user = User::where('telegram_auth_token', trim($token))->first();
            if ($user) {
                $user->telegram_chat_id = (string) $chatId;
                $user->telegram_auth_token = null;
                $user->save();

                $bot->sendMessage(
                    "🎉 *Akun Berhasil Terhubung!*\n\n" .
                    "Halo *{$user->name}*, akun Puslah Anda telah berhasil dihubungkan dengan bot ini.\n\n" .
                    "Mulai sekarang, Anda bisa langsung mengirimkan:\n" .
                    "📸 *Foto dokumentasi kegiatan* (bisa satu atau banyak sekaligus/album).\n" .
                    "📝 *Catatan teks kegiatan* dengan gaya bahasa santai.\n\n" .
                    "Sistem akan otomatis mencatat dan menyusunnya ke dalam bukti dukung kinerja Puslah.",
                    ['parse_mode' => 'Markdown']
                );
                return;
            }

            $bot->sendMessage("⚠️ *Tautan Tidak Valid*\nKode penghubung salah atau sudah digunakan. Silakan buka menu profil di Puslah untuk mendapatkan tautan baru.", ['parse_mode' => 'Markdown']);
            return;
        }

        // 2. Jika tanpa token, cek apakah chat_id sudah terdaftar
        $user = User::where('telegram_chat_id', (string) $chatId)->first();
        if ($user) {
            $bot->sendMessage(
                "Halo *{$user->name}*! 👋\n" .
                "Bot siap menerima bukti dukung dan laporan pekerjaan Anda hari ini.\n\n" .
                "📌 *Perintah Cepat:*\n" .
                "/today - Lihat daftar kegiatan Anda hari ini\n" .
                "/help - Bantuan format pelaporan\n\n" .
                "Silakan kirimkan foto kegiatan atau ketik apa yang sedang Anda kerjakan!",
                ['parse_mode' => 'Markdown']
            );
            return;
        }

        $bot->sendMessage(
            "Selamat datang di *Bot Bukti Dukung Puslah*! 🏛️\n\n" .
            "Akun Telegram Anda belum terhubung dengan akun Puslah.\n" .
            "Silakan login ke web Puslah, buka halaman Profil / Akun, lalu klik tombol *Hubungkan ke Telegram*.",
            ['parse_mode' => 'Markdown']
        );
    }

    /**
     * Handle command /today
     */
    public function handleToday(Nutgram $bot): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $activities = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereDate('activity_date', Carbon::today())
            ->with('attachments')
            ->orderBy('id', 'asc')
            ->get();

        if ($activities->isEmpty()) {
            $bot->sendMessage("📅 *Laporan Hari Ini (" . Carbon::today()->translatedFormat('d F Y') . ")*\n\nBelum ada kegiatan yang tercatat hari ini. Kirim foto atau ketik pekerjaan Anda sekarang untuk mulai mencatat!", ['parse_mode' => 'Markdown']);
            return;
        }

        $text = "📅 *Laporan Kegiatan Hari Ini (" . Carbon::today()->translatedFormat('d F Y') . ")*\n";
        $text .= "Total: *{$activities->count()} kegiatan*\n\n";

        foreach ($activities as $idx => $act) {
            $no = $idx + 1;
            $fotoCount = $act->attachments->count();
            $fotoBadge = $fotoCount > 0 ? " [📷 {$fotoCount} foto]" : "";
            $stBadge = $act->surat_tugas_id ? " (🔖 Terkait ST)" : "";
            $text .= "{$no}. *{$act->final_content}*{$fotoBadge}{$stBadge}\n\n";
        }

        $text .= "_Buka web Puslah untuk mengedit, menambah surat tugas, atau mengunduh laporan format Word/PDF._";

        $bot->sendMessage($text, ['parse_mode' => 'Markdown']);
    }

    /**
     * Handle pesan teks (catatan harian santai)
     */
    public function handleTextMessage(Nutgram $bot): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $rawText = trim($bot->message()->text ?? '');
        if (empty($rawText) || str_starts_with($rawText, '/')) {
            return;
        }

        // Simpan kegiatan baru
        $activity = DailyActivity::create([
            'user_id' => $user->id,
            'activity_date' => Carbon::today(),
            'raw_content' => $rawText,
            'final_content' => $rawText,
            'status' => 'submitted',
            'source' => 'telegram',
        ]);

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✨ Poles Narasi dengan AI',
                    callback_data: "ai_polish:{$activity->id}"
                )
            );

        $bot->sendMessage(
            "✅ *Kegiatan Berhasil Dicatat!*\n\n" .
            "📝 \"{$rawText}\"\n\n" .
            "_Kirim foto dokumentasi jika ada, atau klik tombol di bawah untuk merapikan narasi dengan AI._",
            [
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard,
            ]
        );
    }

    /**
     * Handle foto masuk (single foto maupun album media group)
     */
    public function handlePhotoMessage(Nutgram $bot): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $msg = $bot->message();
        $photos = $msg->photo;
        if (empty($photos)) return;

        // Ambil foto ukuran tertinggi (item terakhir di array photo Telegram)
        $photo = end($photos);
        $caption = trim($msg->caption ?? '');
        $mediaGroupId = $msg->media_group_id ?? null;

        // Download foto dari Telegram
        $file = $bot->getFile($photo->file_id);
        $fileUrl = "https://api.telegram.org/file/bot" . $bot->getToken() . "/" . $file->file_path;

        $ext = pathinfo($file->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        $todayStr = Carbon::today()->format('Y-m-d');
        $fileName = 'activity_' . time() . '_' . uniqid() . '.' . $ext;
        $relativePath = "daily-activities/{$user->id}/{$todayStr}/{$fileName}";

        $fileContents = @file_get_contents($fileUrl);
        if (!$fileContents) {
            $bot->sendMessage("⚠️ Gagal mengunduh foto dari Telegram. Silakan coba kirim ulang.");
            return;
        }

        Storage::disk('public')->put($relativePath, $fileContents);

        // Cari atau buat DailyActivity yang relevan
        $activity = null;

        // Jika bagian dari media_group (album), cari kegiatan yang baru dibuat dengan media_group_id yang sama
        if (!empty($mediaGroupId)) {
            $existingUpdate = TelegramUpdate::where('chat_id', $bot->chatId())
                ->where('media_group_id', $mediaGroupId)
                ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                ->first();

            if ($existingUpdate && !empty($existingUpdate->payload['activity_id'])) {
                $activity = DailyActivity::find($existingUpdate->payload['activity_id']);
            }
        }

        // Jika belum ada activity dari album, atau single photo:
        if (!$activity) {
            $content = !empty($caption) ? $caption : 'Dokumentasi kegiatan';
            $activity = DailyActivity::create([
                'user_id' => $user->id,
                'activity_date' => Carbon::today(),
                'raw_content' => $content,
                'final_content' => $content,
                'status' => 'submitted',
                'source' => 'telegram',
            ]);

            if (!empty($mediaGroupId)) {
                TelegramUpdate::create([
                    'update_id' => $bot->updateId() ?? time(),
                    'chat_id' => $bot->chatId(),
                    'message_id' => $msg->message_id,
                    'media_group_id' => $mediaGroupId,
                    'payload' => ['activity_id' => $activity->id],
                    'processed_at' => Carbon::now(),
                ]);
            }
        }

        // Buat attachment
        DailyActivityAttachment::create([
            'daily_activity_id' => $activity->id,
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'file_size' => strlen($fileContents),
            'mime_type' => 'image/jpeg',
            'is_stamped' => false,
            'caption' => $caption ?: null,
        ]);

        // Kirim konfirmasi (hanya kirim jika single photo atau foto pertama dari album)
        $totalPhotos = $activity->attachments()->count();
        if (empty($mediaGroupId) || $totalPhotos === 1) {
            $keyboard = InlineKeyboardMarkup::make();
            if ($activity->final_content !== 'Dokumentasi kegiatan') {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: '✨ Poles Narasi dengan AI',
                        callback_data: "ai_polish:{$activity->id}"
                    )
                );
            }

            $bot->sendMessage(
                "📸 *Foto Berhasil Disimpan!*\n\n" .
                "📝 Kegiatan: *{$activity->final_content}*\n" .
                "🖼️ Terlampir: *{$totalPhotos} foto*\n\n" .
                "_Foto siap digunakan sebagai bukti dukung di Puslah._",
                [
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $keyboard->isEmpty() ? null : $keyboard,
                ]
            );
        }
    }

    /**
     * Handle callback query inline button (Poles dengan AI)
     */
    public function handleAiPolishCallback(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()->data ?? '';
        if (!str_starts_with($callbackData, 'ai_polish:')) {
            return;
        }

        $activityId = (int) substr($callbackData, 10);
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $activity = DailyActivity::where('id', $activityId)->where('user_id', $user->id)->first();
        if (!$activity) {
            $bot->answerCallbackQuery(text: 'Kegiatan tidak ditemukan atau sudah dihapus.');
            return;
        }

        $bot->answerCallbackQuery(text: 'Sedang memoles narasi dengan Gemini AI...');

        try {
            $aiService = app(DailyActivityAiService::class);
            $formalized = $aiService->formalize($activity->raw_content, [
                'user_name' => $user->name,
                'tanggal' => $activity->activity_date->translatedFormat('d F Y'),
            ]);

            $activity->update([
                'ai_formal_content' => $formalized,
                'final_content' => $formalized,
            ]);

            $bot->sendMessage(
                "✨ *Narasi Berhasil Dipoles AI:*\n\n" .
                "\"{$formalized}\"\n\n" .
                "✅ _Telah diperbarui pada laporan bukti dukung Puslah._",
                ['parse_mode' => 'Markdown']
            );
        } catch (\Throwable $e) {
            Log::error('AI Polish Error via Telegram: ' . $e->getMessage());
            $bot->sendMessage("⚠️ Gagal memoles narasi dengan AI: " . $e->getMessage());
        }
    }

    /**
     * Verifikasi user terdaftar via Telegram chat_id
     */
    protected function getAuthenticatedUser(Nutgram $bot): ?User
    {
        $chatId = $bot->chatId();
        $user = User::where('telegram_chat_id', (string) $chatId)->first();

        if (!$user) {
            $bot->sendMessage(
                "⚠️ *Akun Belum Terhubung*\n" .
                "Silakan buka web Puslah, masuk ke halaman Profil, dan klik tombol *Hubungkan ke Telegram* untuk mulai mencatat.",
                ['parse_mode' => 'Markdown']
            );
            return null;
        }

        return $user;
    }
}
