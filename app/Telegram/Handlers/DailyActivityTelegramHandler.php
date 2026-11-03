<?php

namespace App\Telegram\Handlers;

use App\Models\DailyActivity;
use App\Models\DailyActivityAttachment;
use App\Models\SuratTugas;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\DailyActivityAiService;
use App\Services\ImageExifExtractorService;
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
                    text: "🎉 *Akun Berhasil Terhubung!*\n\n" .
                    "Halo *{$user->name}*, akun Puslah Anda telah berhasil dihubungkan dengan bot ini.\n\n" .
                    "Mulai sekarang, Anda bisa langsung mengirimkan:\n" .
                    "📸 *Foto dokumentasi kegiatan* (bisa satu atau banyak sekaligus/album).\n" .
                    "📝 *Catatan teks kegiatan* dengan gaya bahasa santai.\n\n" .
                    "Sistem akan otomatis mendeteksi Surat Tugas aktif hari ini dan mengekstrak koordinat GPS foto Anda.",
                    chat_id: $chatId,
                    parse_mode: 'Markdown'
                );
                return;
            }

            $bot->sendMessage(
                text: "⚠️ *Tautan Tidak Valid*\nKode penghubung salah atau sudah digunakan. Silakan buka menu profil di Puslah untuk mendapatkan tautan baru.",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return;
        }

        // 2. Jika tanpa token, cek apakah chat_id sudah terdaftar
        $user = User::where('telegram_chat_id', (string) $chatId)->first();
        if ($user) {
            $bot->sendMessage(
                text: "Halo *{$user->name}*! 👋\n" .
                "Bot siap menerima bukti dukung dan laporan pekerjaan Anda hari ini.\n\n" .
                "📌 *Perintah Cepat:*\n" .
                "/today - Lihat daftar kegiatan Anda hari ini\n" .
                "/help - Bantuan format pelaporan\n\n" .
                "Silakan kirimkan foto kegiatan atau ketik apa yang sedang Anda kerjakan!",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return;
        }

        $bot->sendMessage(
            text: "Selamat datang di *Bot Bukti Dukung Puslah*! 🏛️\n\n" .
            "Akun Telegram Anda belum terhubung dengan akun Puslah.\n" .
            "Silakan login ke web Puslah, buka halaman Profil / Akun, lalu klik tombol *Hubungkan ke Telegram*.",
            chat_id: $chatId,
            parse_mode: 'Markdown'
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
            ->with(['attachments', 'suratTugas'])
            ->orderBy('id', 'asc')
            ->get();

        if ($activities->isEmpty()) {
            $bot->sendMessage(
                text: "📅 *Laporan Hari Ini (" . Carbon::today()->translatedFormat('d F Y') . ")*\n\nBelum ada kegiatan yang tercatat hari ini. Kirim foto atau ketik pekerjaan Anda sekarang untuk mulai mencatat!",
                chat_id: $bot->chatId(),
                parse_mode: 'Markdown'
            );
            return;
        }

        $text = "📅 *Laporan Kegiatan Hari Ini (" . Carbon::today()->translatedFormat('d F Y') . ")*\n";
        $text .= "Total: *{$activities->count()} kegiatan*\n\n";

        foreach ($activities as $idx => $act) {
            $no = $idx + 1;
            $fotoCount = $act->attachments->count();
            $fotoBadge = $fotoCount > 0 ? " [📷 {$fotoCount} foto]" : "";
            $stBadge = $act->suratTugas ? " (🔖 No. " . ($act->suratTugas->nomor_surat_tugas ?: 'Draft') . ")" : "";
            $text .= "{$no}. *{$act->final_content}*{$fotoBadge}{$stBadge}\n\n";
        }

        $text .= "_Buka web Puslah untuk mengedit, menambah surat tugas, atau mengunduh laporan format Word/PDF._";

        $bot->sendMessage(
            text: $text,
            chat_id: $bot->chatId(),
            parse_mode: 'Markdown'
        );
    }

    /**
     * Handle command /help
     */
    public function handleHelp(Nutgram $bot): void
    {
        $text = "📖 *Panduan Penggunaan Bot Bukti Dukung Puslah*\n\n" .
            "Bot ini bersifat *dua arah & interaktif* untuk membantu pencatatan kinerja harian Anda secara santai.\n\n" .
            "📌 *Cara Kerja Interaktif:*\n" .
            "1️⃣ *Kirim Catatan Kegiatan:*\n" .
            "Ketik kalimat santai tentang apa yang Anda kerjakan hari ini. Contoh:\n" .
            "_\"Validasi peta blok sensus kec. Karangawen, matching duplikat poligon\"_\n" .
            "👉 *Gemini AI langsung membalas* dengan narasi kedinasan resmi yang rapi dan menyimpannya ke server.\n\n" .
            "2️⃣ *Kirim Foto Dokumentasi:*\n" .
            "Kirim foto kegiatan lapangan atau layar kerja. Bot akan membalas dengan konfirmasi lampiran, jam jepret, dan koordinat lokasi.\n\n" .
            "📌 *Perintah Cepat:*\n" .
            "• `/today` - Lihat rekap seluruh kegiatan Anda hari ini\n" .
            "• `/help` - Buka panduan ini\n\n" .
            "🏛️ _Akses web Puslah untuk mengunduh rekap Word atau menyalin ke Laporan Perjalanan Dinas (LPD)._";

        $bot->sendMessage(
            text: $text,
            chat_id: $bot->chatId(),
            parse_mode: 'Markdown'
        );
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

        // Auto-detect Surat Tugas aktif hari ini
        $activeSt = $this->resolveActiveSuratTugas($user, Carbon::today());

        // Otomatis poles narasi dengan Gemini AI langsung saat diterima
        $formalContent = $rawText;
        try {
            $aiService = app(DailyActivityAiService::class);
            $formalContent = $aiService->formalize($rawText, [
                'user_name' => $user->name,
                'tanggal' => Carbon::today()->translatedFormat('d F Y'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI Polish direct error: ' . $e->getMessage());
        }

        // Simpan kegiatan baru
        $activity = DailyActivity::create([
            'user_id' => $user->id,
            'surat_tugas_id' => $activeSt?->id,
            'activity_date' => Carbon::today(),
            'raw_content' => $rawText,
            'ai_formal_content' => $formalContent,
            'final_content' => $formalContent,
            'status' => 'submitted',
            'source' => 'telegram',
        ]);

        $stInfo = "";
        if ($activeSt) {
            $stNo = $activeSt->nomor_surat_tugas ?: 'DRAFT';
            $stTujuan = $activeSt->keperluan ?: $activeSt->tempat_tugas ?: 'Penugasan Lapangan';
            $stInfo = "\n🔖 *Terkait ST:* No. {$stNo} ({$stTujuan})";
        }

        $bot->sendMessage(
            text: "✅ *Kegiatan Berhasil Dicatat & Dipoles AI!*\n\n" .
            "📝 *Catatan Kasar:*\n_{$rawText}_\n\n" .
            "✨ *Narasi Laporan Formal (AI):*\n*\"{$formalContent}\"*" .
            $stInfo . "\n\n" .
            "💡 _Kirim foto dokumentasi jika ada. Laporan Anda sudah tersimpan otomatis di Puslah!_",
            chat_id: $bot->chatId(),
            parse_mode: 'Markdown'
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
        if (!$file || empty($file->file_path)) {
            $bot->sendMessage(
                text: "⚠️ Gagal membaca data file foto dari Telegram.",
                chat_id: $bot->chatId()
            );
            return;
        }

        $ext = pathinfo($file->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        $todayStr = Carbon::today()->format('Y-m-d');
        $fileName = 'activity_' . time() . '_' . uniqid() . '.' . $ext;
        $relativePath = "daily-activities/{$user->id}/{$todayStr}/{$fileName}";
        $fullPath = storage_path('app/public/' . $relativePath);

        // Pastikan folder penyimpanan ada
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // 1. Coba download via Nutgram downloadFile
        $downloaded = false;
        try {
            $downloaded = $bot->downloadFile($file, $fullPath);
        } catch (\Throwable $e) {
            Log::warning("Nutgram downloadFile error: " . $e->getMessage());
        }

        // 2. Fallback via URL jika belum terdownload
        if (!$downloaded || !file_exists($fullPath) || filesize($fullPath) === 0) {
            $token = config('nutgram.token');
            $fileUrl = "https://api.telegram.org/file/bot{$token}/" . $file->file_path;
            $fileContents = @file_get_contents($fileUrl);
            if ($fileContents) {
                file_put_contents($fullPath, $fileContents);
                $downloaded = true;
            }
        }

        if (!file_exists($fullPath) || filesize($fullPath) === 0) {
            $bot->sendMessage(
                text: "⚠️ Gagal mengunduh foto dari server Telegram. Silakan coba kirim ulang.",
                chat_id: $bot->chatId()
            );
            return;
        }

        $fileSize = filesize($fullPath);

        // Ekstraksi EXIF GPS dan Waktu Pengambilan
        $exifService = app(ImageExifExtractorService::class);
        $exifData = $exifService->extract($fullPath);

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
            $rawContent = !empty($caption) ? $caption : 'Dokumentasi kegiatan';
            $formalContent = $rawContent;

            // Jika ada caption, poles otomatis dengan AI
            if (!empty($caption)) {
                try {
                    $aiService = app(DailyActivityAiService::class);
                    $formalContent = $aiService->formalize($caption, [
                        'user_name' => $user->name,
                        'tanggal' => Carbon::today()->translatedFormat('d F Y'),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('AI Polish photo caption error: ' . $e->getMessage());
                }
            }

            $activeSt = $this->resolveActiveSuratTugas($user, Carbon::today());

            $activity = DailyActivity::create([
                'user_id' => $user->id,
                'surat_tugas_id' => $activeSt?->id,
                'activity_date' => Carbon::today(),
                'raw_content' => $rawContent,
                'ai_formal_content' => $formalContent,
                'final_content' => $formalContent,
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

        // Tentukan waktu foto: gunakan EXIF jika ada, atau waktu pesan Telegram saat dikirim
        $capturedAt = $exifData['captured_at'] ?? Carbon::createFromTimestamp($msg->date ?? time());

        // Tentukan koordinat: gunakan EXIF jika ada; jika kosong (kamera in-app Telegram), gunakan default kantor
        $officeCoords = $this->getOfficeCoordinates();
        $isDefaultOffice = false;

        if (!empty($exifData['latitude']) && !empty($exifData['longitude'])) {
            $latitude = $exifData['latitude'];
            $longitude = $exifData['longitude'];
        } else {
            $latitude = $officeCoords['latitude'];
            $longitude = $officeCoords['longitude'];
            $isDefaultOffice = true;
        }

        // Buat attachment dengan data EXIF / default kantor
        DailyActivityAttachment::create([
            'daily_activity_id' => $activity->id,
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => 'image/jpeg',
            'is_stamped' => false,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'captured_at' => $capturedAt,
            'caption' => $caption ?: null,
        ]);

        // Kirim konfirmasi (hanya kirim jika single photo atau foto pertama dari album)
        $totalPhotos = $activity->attachments()->count();
        if (empty($mediaGroupId) || $totalPhotos === 1) {
            $stBadge = "";
            if ($activity->suratTugas) {
                $stNo = $activity->suratTugas->nomor_surat_tugas ?: 'DRAFT';
                $stBadge = "\n🔖 *Terkait ST:* No. {$stNo}";
            }

            $gpsBadge = "";
            if (!$isDefaultOffice) {
                $gpsBadge = "\n📍 *GPS Terdeteksi:* `{$latitude}, {$longitude}`";
            } else {
                $gpsBadge = "\n📍 *GPS:* `{$latitude}, {$longitude}` _(Default Kantor: {$officeCoords['name']})_";
            }

            $timeBadge = "\n🕒 *Waktu:* " . $capturedAt->translatedFormat('d M Y H:i') . " WIB";

            $captionInfo = "";
            if (!empty($caption)) {
                $captionInfo = "\n📝 *Catatan:* \"{$caption}\"\n✨ *Narasi AI:* \"{$activity->final_content}\"\n";
            } else {
                $captionInfo = "\n📝 *Kegiatan:* {$activity->final_content}\n";
            }

            $bot->sendMessage(
                text: "📸 *Foto Berhasil Disimpan!*\n" .
                $captionInfo .
                "🖼️ *Lampiran:* {$totalPhotos} foto" .
                $stBadge .
                $gpsBadge .
                $timeBadge . "\n\n" .
                "✅ _Tersimpan otomatis di bukti dukung Puslah._",
                chat_id: $bot->chatId(),
                parse_mode: 'Markdown'
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
                text: "✨ *Narasi Berhasil Dipoles AI:*\n\n" .
                "\"{$formalized}\"\n\n" .
                "✅ _Telah diperbarui pada laporan bukti dukung Puslah._",
                chat_id: $bot->chatId(),
                parse_mode: 'Markdown'
            );
        } catch (\Throwable $e) {
            Log::error('AI Polish Error via Telegram: ' . $e->getMessage());
            $bot->sendMessage(
                text: "⚠️ Gagal memoles narasi dengan AI: " . $e->getMessage(),
                chat_id: $bot->chatId()
            );
        }
    }

    /**
     * Auto-detect Surat Tugas aktif pegawai pada tanggal tertentu.
     */
    public function resolveActiveSuratTugas(User $user, Carbon $date): ?SuratTugas
    {
        $todayStr = $date->toDateString();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $activeStList = SuratTugas::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($todayStr, $startOfDay, $endOfDay) {
                $q->whereDate('tanggal', $todayStr)
                    ->orWhere(function ($sub) use ($startOfDay, $endOfDay) {
                        $sub->where('waktu_mulai', '<=', $endOfDay)
                            ->where('waktu_selesai', '>=', $startOfDay);
                    });
            })
            ->where('status', '!=', 'rejected')
            ->get();

        // Jika tepat 1 penugasan aktif, otomatis kaitkan
        if ($activeStList->count() === 1) {
            return $activeStList->first();
        }

        return null;
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
                text: "⚠️ *Akun Belum Terhubung*\n" .
                "Silakan buka web Puslah, masuk ke halaman Profil, dan klik tombol *Hubungkan ke Telegram* untuk mulai mencatat.",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return null;
        }

        return $user;
    }

    /**
     * Dapatkan koordinat kantor default BPS Demak dari SystemSettings atau fallback.
     */
    public function getOfficeCoordinates(): array
    {
        try {
            $settings = app(\App\Settings\SystemSettings::class);
            if (!empty($settings->default_office_lat) && !empty($settings->default_office_lng) && $settings->default_office_lat != 0) {
                return [
                    'latitude' => round((float) $settings->default_office_lat, 8),
                    'longitude' => round((float) $settings->default_office_lng, 8),
                    'name' => $settings->default_office_name ?? 'BPS Kabupaten Demak',
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'latitude' => -6.894561,
            'longitude' => 110.637492,
            'name' => 'BPS Kabupaten Demak',
        ];
    }
}
