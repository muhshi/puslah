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
use Illuminate\Support\Facades\Cache;
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
     * Handle command /rekap: Rekap capaian hari kerja dan daftar hari kosong bulan berjalan
     */
    public function handleRekap(Nutgram $bot): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $startOfMonth = Carbon::today()->startOfMonth();
        $today = Carbon::today();

        // Kumpulkan hari kerja (Senin - Jumat) dari awal bulan sampai hari ini
        $workdays = [];
        $cur = $startOfMonth->copy();
        while ($cur->lte($today)) {
            if (!$cur->isWeekend()) {
                $workdays[] = $cur->copy();
            }
            $cur->addDay();
        }

        $totalWorkdays = count($workdays);
        if ($totalWorkdays === 0) {
            $bot->sendMessage(
                text: "Belum ada hari kerja pada bulan ini.",
                chat_id: $bot->chatId()
            );
            return;
        }

        // Ambil semua tanggal yang memiliki kegiatan
        $filledDates = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('activity_date', [$startOfMonth->toDateString(), $today->toDateString()])
            ->pluck('activity_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->toArray();

        $filledCount = 0;
        $missingDays = [];

        foreach ($workdays as $wd) {
            $dateStr = $wd->toDateString();
            if (in_array($dateStr, $filledDates)) {
                $filledCount++;
            } else {
                $missingDays[] = $wd;
            }
        }

        $percent = round(($filledCount / $totalWorkdays) * 100);
        $monthName = $today->translatedFormat('F Y');

        $message = "📊 *Rekap Bukti Dukung Bulan {$monthName}*\n";
        $message .= "Pegawai: *{$user->name}*\n\n";
        $message .= "📈 *Capaian:* {$filledCount} dari {$totalWorkdays} hari kerja terisi (*{$percent}%*)\n";

        if (empty($missingDays)) {
            $message .= "\n🎉 *Luar Biasa!* Seluruh hari kerja bulan ini sudah terisi lengkap dengan laporan bukti dukung. Kinerja Anda siap dievaluasi!";
        } else {
            $missingCount = count($missingDays);
            $message .= "\n⚠️ *{$missingCount} Hari Kerja Belum Ada Laporan:*\n";
            foreach ($missingDays as $md) {
                $message .= "• " . $md->translatedFormat('l, d F Y') . "\n";
            }
            $message .= "\n💡 _Kirim foto dokumentasi atau ketik laporan dengan menyebut tanggalnya (misal: \"tgl " . $missingDays[0]->format('d/m') . ": kegiatan...\") untuk melengkapi backdate!_";
        }

        $bot->sendMessage(
            text: $message,
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
            "• `/rekap` - Lihat capaian hari kerja dan tanggal kosong bulan ini\n" .
            "• `/stempel` - Atur stempel foto (matikan jika kamera HP sudah ber-watermark)\n" .
            "• `/help` - Buka panduan ini\n\n" .
            "🏛️ _Akses web Puslah untuk mengunduh rekap Word atau menyalin ke Laporan Perjalanan Dinas (LPD)._";

        $bot->sendMessage(
            text: $text,
            chat_id: $bot->chatId(),
            parse_mode: 'Markdown'
        );
    }

    /**
     * Handle command /stempel: Mengatur preferensi auto-stempel foto (on/off/posisi)
     */
    public function handleStempel(Nutgram $bot, ?string $mode = null): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $chatId = $bot->chatId();
        $arg = strtolower(trim($mode ?? ''));

        if (in_array($arg, ['off', 'mati', 'disable', 'nonaktif'])) {
            Cache::forever("tg_user_stamp_pref_{$user->id}", false);

            $bot->sendMessage(
                text: "🔕 *Auto-Stempel Foto Dinonaktifkan!*\n\n" .
                "Mulai sekarang, foto kegiatan yang Anda kirim ke bot *tidak akan dibakar stempel lagi* oleh sistem Puslah.\n\n" .
                "✅ *Anti Dobel Watermark:* Sangat cocok untuk Anda yang kameranya sudah memiliki watermark bawaan ponsel (seperti iQOO, Xiaomi, Samsung, Vivo) atau aplikasi Open Camera agar *foto tetap bersih dan tidak timbul watermark bertumpuk*.\n\n" .
                "💡 Ketik `/stempel on` kapan pun Anda ingin mengaktifkan stempel kembali.",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return;
        }

        if (in_array($arg, ['on', 'aktif', 'enable', 'hidup'])) {
            Cache::forever("tg_user_stamp_pref_{$user->id}", true);

            $bot->sendMessage(
                text: "🔔 *Auto-Stempel Foto Diaktifkan!*\n\n" .
                "Setiap foto kegiatan yang Anda kirim ke bot akan otomatis dibubuhi stempel resmi BPS (koordinat GPS dan waktu WIB).\n\n" .
                "💡 Ketik `/stempel off` jika ingin mematikannya kembali.",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return;
        }

        if (str_starts_with($arg, 'posisi') || in_array($arg, ['kanan-bawah', 'kiri-bawah', 'kiri-atas', 'kanan-atas', 'br', 'bl', 'tl', 'tr'])) {
            $pos = str_replace('posisi', '', $arg);
            $pos = trim($pos);

            $mappedPos = match ($pos) {
                'kiri-bawah', 'bl' => 'bottom-left',
                'kiri-atas', 'tl' => 'top-left',
                'kanan-atas', 'tr' => 'top-right',
                default => 'bottom-right',
            };

            $label = match ($mappedPos) {
                'bottom-left' => 'Pojok Kiri Bawah',
                'top-left' => 'Pojok Kiri Atas',
                'top-right' => 'Pojok Kanan Atas',
                default => 'Pojok Kanan Bawah (Standar Aman)',
            };

            Cache::forever("tg_user_stamp_pos_{$user->id}", $mappedPos);

            $bot->sendMessage(
                text: "📍 *Posisi Stempel Diperbarui!*\n\nPosisi stempel foto Anda sekarang: *{$label}*.\nStempel akan ditempatkan pada posisi ini sehingga tidak menimpa objek penting atau watermark ponsel Anda.",
                chat_id: $chatId,
                parse_mode: 'Markdown'
            );
            return;
        }

        // Tampilkan status & menu bantuan
        $isStamped = Cache::get("tg_user_stamp_pref_{$user->id}", true);
        $currentPos = Cache::get("tg_user_stamp_pos_{$user->id}", 'bottom-right');
        $posLabel = match ($currentPos) {
            'bottom-left' => 'Pojok Kiri Bawah',
            'top-left' => 'Pojok Kiri Atas',
            'top-right' => 'Pojok Kanan Atas',
            default => 'Pojok Kanan Bawah (Standar Aman)',
        };

        $statusBadge = $isStamped ? "🟢 *AKTIF* (Foto otomatis distempel)" : "🔴 *NONAKTIF* (Foto asli tanpa stempel)";

        $text = "⚙️ *Pengaturan Stempel Foto Bukti Dukung*\n\n" .
            "Pegawai: *{$user->name}*\n" .
            "Status Stempel: {$statusBadge}\n" .
            "Posisi Stempel: *{$posLabel}*\n\n" .
            "📌 *Perintah Cepat:*\n" .
            "• `/stempel off` - Matikan stempel (cegah dobel watermark jika kamera HP sudah ber-watermark)\n" .
            "• `/stempel on` - Nyalakan stempel otomatis\n" .
            "• `/stempel posisi kanan-bawah` - Ubah posisi ke kanan bawah\n" .
            "• `/stempel posisi kiri-atas` - Ubah posisi ke kiri atas\n" .
            "• `/stempel posisi kanan-atas` - Ubah posisi ke kanan atas\n\n" .
            "💡 _Anda juga bisa menggunakan hashtag langsung pada caption foto: `#nostamp` untuk tanpa stempel, `#kiriatas`, `#kananatas`, atau `#kananbawah`._";

        $bot->sendMessage(
            text: $text,
            chat_id: $chatId,
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

        // Parse tanggal kegiatan cerdas (hari ini atau backdate lampau)
        $activityDate = $this->resolveActivityDate($rawText);

        // Deteksi Surat Tugas HANYA jika pesan menyebutkan indikasi ST/surat tugas atau penugasan spesifik
        $activeSt = $this->resolveActiveSuratTugas($user, $activityDate, $rawText);

        // Auto-detect wadah KIPAPP jika ada hashtag (misal: #wilkerstat, #inovasi)
        $sharedReports = $this->resolveSharedWorkReports($user, $rawText);

        // Otomatis poles narasi dengan Gemini AI langsung saat diterima
        $formalContent = $rawText;
        try {
            $aiService = app(DailyActivityAiService::class);
            $formalContent = $aiService->formalize($rawText, [
                'user_name' => $user->name,
                'tanggal' => $activityDate->translatedFormat('d F Y'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI Polish direct error: ' . $e->getMessage());
        }

        // Simpan kegiatan baru
        $activity = DailyActivity::create([
            'user_id' => $user->id,
            'surat_tugas_id' => $activeSt?->id,
            'shared_work_report_id' => $sharedReports->first()?->id,
            'activity_date' => $activityDate,
            'raw_content' => $rawText,
            'ai_formal_content' => $formalContent,
            'final_content' => $formalContent,
            'status' => 'submitted',
            'source' => 'telegram',
        ]);

        if ($sharedReports->isNotEmpty()) {
            $activity->sharedWorkReports()->sync($sharedReports->pluck('id'));
        }

        $dateNotice = "📅 *Tanggal Kegiatan:* " . $activityDate->translatedFormat('l, d F Y');
        if (!$activityDate->isToday()) {
            $dateNotice .= " _(Backdate)_";
        }

        $stInfo = "";
        if ($activeSt) {
            $stNo = $activeSt->nomor_surat_tugas ?: 'DRAFT';
            $stTujuan = $activeSt->keperluan ?: $activeSt->tempat_tugas ?: 'Penugasan Lapangan';
            $stInfo = "\n🔖 *Terkait ST:* No. {$stNo} ({$stTujuan})";
        }

        $kipappInfo = "";
        if ($sharedReports->isNotEmpty()) {
            $wadahNames = $sharedReports->pluck('title')->implode(', ');
            $kipappInfo = "\n📁 *Wadah KIPAPP:* {$wadahNames}";
        }

        $bot->sendMessage(
            text: "✅ *Kegiatan Berhasil Dicatat & Dipoles AI!*\n\n" .
            $dateNotice . "\n\n" .
            "📝 *Catatan Kasar:*\n_{$rawText}_\n\n" .
            "✨ *Narasi Laporan Formal (AI):*\n*\"{$formalContent}\"*" .
            $stInfo . $kipappInfo . "\n\n" .
            "💡 _Kirim foto/dokumen jika ada. Laporan Anda sudah tersimpan otomatis di Puslah!_",
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

        // Parse tanggal kegiatan cerdas (dari caption atau tanggal EXIF foto asli)
        $activityDate = $this->resolveActivityDate($caption ?: '', $exifData['captured_at'] ?? null);

        // Kunci atomic lock jika media_group (album) untuk mencegah race condition antar foto
        $albumLock = null;
        if (!empty($mediaGroupId)) {
            try {
                $albumLock = Cache::lock("tg_album_{$bot->chatId()}_{$mediaGroupId}", 30);
                $albumLock->block(15);
            } catch (\Throwable $e) {
                Log::warning('Album lock warning: ' . $e->getMessage());
            }
        }

        try {
            // Cari atau buat DailyActivity yang relevan
            $activity = null;
            $existingUpdate = null;

            // Jika bagian dari media_group (album), cari kegiatan yang baru dibuat dengan media_group_id yang sama
            if (!empty($mediaGroupId)) {
                $existingUpdate = TelegramUpdate::where('chat_id', $bot->chatId())
                    ->where('media_group_id', $mediaGroupId)
                    ->where('created_at', '>=', Carbon::now()->subMinutes(10))
                    ->latest()
                    ->first();

                if ($existingUpdate && !empty($existingUpdate->payload['activity_id'])) {
                    $activity = DailyActivity::find($existingUpdate->payload['activity_id']);
                }
            }

            // Jika activity sudah ada (foto ke-2 dst di album) dan ada caption baru:
            // Telegram hanya menempelkan caption pada 1 foto di album (bisa foto pertama atau foto berikutnya).
            // Jika foto berikutnya membawa caption, update activity yang ada agar caption tidak hilang!
            if ($activity && !empty($caption)) {
                $isOldDefault = empty($activity->raw_content) || $activity->raw_content === 'Dokumentasi kegiatan';
                if ($isOldDefault || strlen($caption) > strlen($activity->raw_content)) {
                    $activity->raw_content = $caption;

                    // Update tanggal kegiatan jika di caption baru ada keterangan backdate
                    $newActivityDate = $this->resolveActivityDate($caption, $exifData['captured_at'] ?? null);
                    $activity->activity_date = $newActivityDate;

                    // Poles caption baru dengan AI
                    try {
                        $aiService = app(DailyActivityAiService::class);
                        $formalContent = $aiService->formalize($caption, [
                            'user_name' => $user->name,
                            'tanggal' => $activity->activity_date->translatedFormat('d F Y'),
                        ]);
                        $activity->ai_formal_content = $formalContent;
                        $activity->final_content = $formalContent;
                    } catch (\Throwable $e) {
                        Log::warning('AI Polish album caption error: ' . $e->getMessage());
                        $activity->ai_formal_content = $caption;
                        $activity->final_content = $caption;
                    }

                    // Update Surat Tugas dan Wadah KIPAPP
                    $activeSt = $this->resolveActiveSuratTugas($user, $activity->activity_date, $caption);
                    if ($activeSt) {
                        $activity->surat_tugas_id = $activeSt->id;
                    }
                    $sharedReports = $this->resolveSharedWorkReports($user, $caption);
                    if ($sharedReports->isNotEmpty()) {
                        $activity->shared_work_report_id = $sharedReports->first()->id;
                        $activity->sharedWorkReports()->sync($sharedReports->pluck('id'));
                    }

                    $activity->save();
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
                            'tanggal' => $activityDate->translatedFormat('d F Y'),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('AI Polish photo caption error: ' . $e->getMessage());
                    }
                }

                $activeSt = $this->resolveActiveSuratTugas($user, $activityDate, $caption ?: '');
                $sharedReports = $this->resolveSharedWorkReports($user, $caption ?: '');

                $activity = DailyActivity::create([
                    'user_id' => $user->id,
                    'surat_tugas_id' => $activeSt?->id,
                    'shared_work_report_id' => $sharedReports->first()?->id,
                    'activity_date' => $activityDate,
                    'raw_content' => $rawContent,
                    'ai_formal_content' => $formalContent,
                    'final_content' => $formalContent,
                    'status' => 'submitted',
                    'source' => 'telegram',
                ]);

                if ($sharedReports->isNotEmpty()) {
                    $activity->sharedWorkReports()->sync($sharedReports->pluck('id'));
                }

                if (!empty($mediaGroupId)) {
                    $existingUpdate = TelegramUpdate::create([
                        'update_id' => $bot->updateId() ?? time(),
                        'chat_id' => $bot->chatId(),
                        'message_id' => $msg->message_id,
                        'media_group_id' => $mediaGroupId,
                        'payload' => ['activity_id' => $activity->id],
                        'processed_at' => Carbon::now(),
                    ]);
                }
            }

            // Tentukan waktu foto: gunakan EXIF jika ada, atau waktu pesan Telegram saat dikirim (konversi ke WIB)
            $capturedAt = $exifData['captured_at'] ?? Carbon::createFromTimestamp($msg->date ?? time(), 'Asia/Jakarta')->setTimezone('Asia/Jakarta');

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

            // Cek preferensi auto-stempel pengguna dan instruksi hashtag caption
            $userAutoStamp = Cache::get("tg_user_stamp_pref_{$user->id}", true);
            $effectiveCaption = !empty($caption) ? $caption : $activity->raw_content;
            $noStampRequested = str_contains(strtolower($effectiveCaption), '#nostamp') 
                || str_contains(strtolower($effectiveCaption), '#nowatermark') 
                || str_contains(strtolower($effectiveCaption), '#tanpastempel');

            $shouldStamp = $userAutoStamp && !$noStampRequested;

            // Posisi stempel: prioritaskan hashtag di caption, lalu preferensi user, fallback bottom-right
            $userPrefPos = Cache::get("tg_user_stamp_pos_{$user->id}", 'bottom-right');
            $stampPosition = $userPrefPos;

            if (str_contains(strtolower($effectiveCaption), '#kiribawah') || str_contains(strtolower($effectiveCaption), '#bl')) {
                $stampPosition = 'bottom-left';
            } elseif (str_contains(strtolower($effectiveCaption), '#kiriatas') || str_contains(strtolower($effectiveCaption), '#tl')) {
                $stampPosition = 'top-left';
            } elseif (str_contains(strtolower($effectiveCaption), '#kananatas') || str_contains(strtolower($effectiveCaption), '#tr')) {
                $stampPosition = 'top-right';
            } elseif (str_contains(strtolower($effectiveCaption), '#kananbawah') || str_contains(strtolower($effectiveCaption), '#br')) {
                $stampPosition = 'bottom-right';
            }

            // Terapkan watermark stempel fisik permanen ke file foto (PHP GD) jika diizinkan
            $watermarkSuccess = false;
            if ($shouldStamp) {
                try {
                    $watermarkService = app(\App\Services\PhotoWatermarkService::class);
                    $watermarkSuccess = $watermarkService->applyWatermark($fullPath, [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'captured_at' => $capturedAt,
                        'caption' => $effectiveCaption,
                        'office_name' => $officeCoords['name'] ?? 'BPS KABUPATEN DEMAK',
                        'position' => $stampPosition,
                    ]);
                    if ($watermarkSuccess) {
                        clearstatcache(true, $fullPath);
                        $fileSize = filesize($fullPath);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gagal membakar watermark foto telegram: ' . $e->getMessage());
                }
            }

            // Buat attachment: jika sengaja tidak distempel (misal kamera sudah ada watermark bawaan),
            // set is_stamped = true agar event booted model tidak membakar stempel otomatis di database
            DailyActivityAttachment::create([
                'daily_activity_id' => $activity->id,
                'file_path' => $relativePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => 'image/jpeg',
                'is_stamped' => $watermarkSuccess || !$shouldStamp,
                'stamp_position' => $stampPosition,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'captured_at' => $capturedAt,
                'caption' => $caption ?: null,
            ]);

            // Format data badge untuk pesan konfirmasi
            $totalPhotos = $activity->attachments()->count();

            $stBadge = "";
            if ($activity->suratTugas) {
                $stNo = $activity->suratTugas->nomor_surat_tugas ?: 'DRAFT';
                $stBadge = "\n🔖 *Terkait ST:* No. {$stNo}";
            }

            $kipappBadge = "";
            $allReports = $activity->sharedWorkReports;
            if ($allReports->isEmpty() && $activity->sharedWorkReport) {
                $allReports = collect([$activity->sharedWorkReport]);
            }
            if ($allReports->isNotEmpty()) {
                $wadahList = $allReports->pluck('title')->implode(', ');
                $kipappBadge = "\n📁 *Wadah KIPAPP:* {$wadahList}";
            }

            $gpsBadge = "";
            if (!$isDefaultOffice) {
                $gpsBadge = "\n📍 *GPS Terdeteksi:* `{$latitude}, {$longitude}`";
            } else {
                $gpsBadge = "\n📍 *GPS:* `{$latitude}, {$longitude}` _(Default Kantor: {$officeCoords['name']})_";
            }

            $timeBadge = "\n🕒 *Waktu:* " . $capturedAt->translatedFormat('d M Y H:i') . " WIB";

            $captionInfo = "";
            if (!empty($activity->raw_content) && $activity->raw_content !== 'Dokumentasi kegiatan') {
                $captionInfo = "\n📝 *Catatan:* \"{$activity->raw_content}\"\n✨ *Narasi AI:* \"{$activity->final_content}\"\n";
            } else {
                $captionInfo = "\n📝 *Kegiatan:* {$activity->final_content}\n";
            }

            $dateBadge = "\n📅 *Tanggal Kegiatan:* " . $activity->activity_date->translatedFormat('l, d F Y') . (!$activity->activity_date->isToday() ? " _(Backdate)_" : "");

            $stampBadge = $shouldStamp ? "" : "\n🛡️ *Watermark:* _Asli Kamera Bawaan (Anti Dobel Stempel)_";

            $responseMsgText = "📸 *Foto Berhasil Disimpan!*\n" .
                $captionInfo .
                "🖼️ *Lampiran:* {$totalPhotos} berkas" .
                $dateBadge .
                $stBadge .
                $kipappBadge .
                $gpsBadge .
                $timeBadge .
                $stampBadge . "\n\n" .
                "✅ _Tersimpan otomatis di bukti dukung Puslah._";

            if (empty($mediaGroupId)) {
                // Single photo: kirim konfirmasi langsung
                $bot->sendMessage(
                    text: $responseMsgText,
                    chat_id: $bot->chatId(),
                    parse_mode: 'Markdown'
                );
            } else {
                // Album (Media Group): Coba edit pesan sebelumnya agar update real-time
                $botMsgId = $existingUpdate?->payload['response_message_id'] ?? null;
                $edited = false;

                if ($botMsgId) {
                    try {
                        $bot->editMessageText(
                            text: $responseMsgText,
                            chat_id: $bot->chatId(),
                            message_id: (int) $botMsgId,
                            parse_mode: 'Markdown'
                        );
                        $edited = true;
                    } catch (\Throwable $e) {
                        Log::info("Nutgram editMessageText album note: " . $e->getMessage());
                    }
                }

                // Jika belum diedit (misal foto pertama dari album, atau editMessageText gagal):
                if (!$edited && $totalPhotos === 1) {
                    $sent = $bot->sendMessage(
                        text: $responseMsgText,
                        chat_id: $bot->chatId(),
                        parse_mode: 'Markdown'
                    );
                    if ($sent && $existingUpdate) {
                        $payload = $existingUpdate->payload ?? [];
                        $payload['response_message_id'] = $sent->message_id;
                        $existingUpdate->update(['payload' => $payload]);
                    }
                } elseif (!$edited && $totalPhotos > 1) {
                    // Jika edit gagal dan foto bertambah, kirim notifikasi pembaruan lampiran
                    $sent = $bot->sendMessage(
                        text: $responseMsgText,
                        chat_id: $bot->chatId(),
                        parse_mode: 'Markdown'
                    );
                    if ($sent && $existingUpdate) {
                        $payload = $existingUpdate->payload ?? [];
                        $payload['response_message_id'] = $sent->message_id;
                        $existingUpdate->update(['payload' => $payload]);
                    }
                }
            }
        } finally {
            if ($albumLock) {
                try {
                    $albumLock->release();
                } catch (\Throwable $e) {
                    // Abaikan release exception
                }
            }
        }
    }

    /**
     * Handle pesan suara / voice note dan audio langsung dari lapangan.
     */
    public function handleVoiceMessage(Nutgram $bot): void
    {
        $user = $this->getAuthenticatedUser($bot);
        if (!$user) return;

        $msg = $bot->message();
        $voice = $msg->voice ?? $msg->audio;
        if (!$voice || empty($voice->file_id)) return;

        $bot->sendChatAction(\SergiX44\Nutgram\Telegram\Properties\ChatAction::RECORD_VOICE);

        // Download file suara dari Telegram
        $file = $bot->getFile($voice->file_id);
        if (!$file || empty($file->file_path)) {
            $bot->sendMessage(text: "⚠️ Gagal membaca berkas pesan suara dari Telegram.", chat_id: $bot->chatId());
            return;
        }

        $ext = pathinfo($file->file_path, PATHINFO_EXTENSION) ?: 'ogg';
        $tempPath = storage_path('app/temp_voice_' . time() . '_' . uniqid() . '.' . $ext);

        $downloaded = false;
        try {
            $downloaded = $bot->downloadFile($file, $tempPath);
        } catch (\Throwable $e) {
            Log::warning("Voice downloadFile error: " . $e->getMessage());
        }

        if (!$downloaded || !file_exists($tempPath) || filesize($tempPath) === 0) {
            $token = config('nutgram.token');
            $fileUrl = "https://api.telegram.org/file/bot{$token}/" . $file->file_path;
            $contents = @file_get_contents($fileUrl);
            if ($contents) {
                file_put_contents($tempPath, $contents);
                $downloaded = true;
            }
        }

        if (!$downloaded || !file_exists($tempPath)) {
            $bot->sendMessage(text: "⚠️ Gagal mengunduh berkas audio dari Telegram.", chat_id: $bot->chatId());
            return;
        }

        try {
            $bot->sendMessage(text: "🎙️ *Mendengarkan rekaman suara & memoles laporan dengan Gemini AI...*", chat_id: $bot->chatId(), parse_mode: 'Markdown');

            $aiService = app(DailyActivityAiService::class);
            $mimeType = str_contains($ext, 'mp3') ? 'audio/mp3' : 'audio/ogg';
            $aiResult = $aiService->formalizeAudio($tempPath, $mimeType, [
                'user_name' => $user->name,
                'tanggal' => Carbon::today()->translatedFormat('d F Y'),
            ]);

            $rawTranscript = $aiResult['raw_transcript'];
            $formalContent = $aiResult['formal_content'];

            // Deteksi Tanggal, ST dan Wadah KIPAPP
            $combinedText = $rawTranscript . " " . ($msg->caption ?? '');
            $activityDate = $this->resolveActivityDate($combinedText);
            $activeSt = $this->resolveActiveSuratTugas($user, $activityDate, $combinedText);
            $sharedReports = $this->resolveSharedWorkReports($user, $combinedText);

            $activity = DailyActivity::create([
                'user_id' => $user->id,
                'surat_tugas_id' => $activeSt?->id,
                'shared_work_report_id' => $sharedReports->first()?->id,
                'activity_date' => $activityDate,
                'raw_content' => '[Pesan Suara] ' . $rawTranscript,
                'ai_formal_content' => $formalContent,
                'final_content' => $formalContent,
                'status' => 'submitted',
                'source' => 'telegram_voice',
            ]);

            if ($sharedReports->isNotEmpty()) {
                $activity->sharedWorkReports()->sync($sharedReports->pluck('id'));
            }

            $dateNotice = "📅 *Tanggal Kegiatan:* " . $activityDate->translatedFormat('l, d F Y') . (!$activityDate->isToday() ? " _(Backdate)_" : "");

            $stInfo = "";
            if ($activeSt) {
                $stNo = $activeSt->nomor_surat_tugas ?: 'DRAFT';
                $stInfo = "\n🔖 *Terkait ST:* No. {$stNo}";
            }

            $kipappInfo = "";
            if ($sharedReports->isNotEmpty()) {
                $wadahNames = $sharedReports->pluck('title')->implode(', ');
                $kipappInfo = "\n📁 *Wadah KIPAPP:* {$wadahNames}";
            }

            $bot->sendMessage(
                text: "🎙️ *Laporan Suara Berhasil Dicatat!*\n\n" .
                $dateNotice . "\n\n" .
                "🗣️ *Transkrip Ucapan:*\n_{$rawTranscript}_\n\n" .
                "✨ *Narasi Laporan Formal (AI):*\n*\"{$formalContent}\"*" .
                $stInfo . $kipappInfo . "\n\n" .
                "✅ _Laporan kinerja harian Anda sudah otomatis tersimpan di Puslah!_",
                chat_id: $bot->chatId(),
                parse_mode: 'Markdown'
            );
        } catch (\Throwable $e) {
            Log::error("Voice processing error: " . $e->getMessage());
            $bot->sendMessage(
                text: "⚠️ Terjadi kendala saat memproses rekaman suara dengan AI: " . $e->getMessage() . "\n\nSilakan kirimkan laporan dalam bentuk teks atau coba lagi.",
                chat_id: $bot->chatId()
            );
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
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
     * Deteksi Surat Tugas aktif pegawai.
     * Hanya mengaitkan jika pesan menyebutkan indikasi 'st' atau 'surat tugas', atau nomor spesifik,
     * untuk mencegah pekerjaan rutin tersambung ke random surat tugas.
     */
    /**
     * Resolusi Surat Tugas aktif:
     * Default SELALU KOSONG (null) sesuai alur kedinasan BPS.
     * Pekerjaan harian yang dikirim via Telegram default-nya adalah kegiatan rutin/internal kantor non-surat tugas.
     * Surat Tugas hanya akan dikaitkan jika user secara EKSPLISIT menyertakan tag nomor ST (misal: #st4467 atau #st:4467).
     */
    public function resolveActiveSuratTugas(User $user, Carbon $date, string $text = ''): ?SuratTugas
    {
        if (empty($text)) {
            return null;
        }

        // Hanya deteksi jika ada hashtag eksplisit dengan nomor ST, misal: #st4467, #st:4467, #st-4467
        if (preg_match('/#st[:\s_-]?(\d{3,})/i', $text, $matches)) {
            $stNumber = $matches[1];
            return SuratTugas::where('user_id', $user->id)
                ->where(function ($q) use ($stNumber) {
                    $q->where('id', $stNumber)
                      ->orWhere('nomor_surat_tugas', 'like', "%{$stNumber}%");
                })
                ->where('status', '!=', 'rejected')
                ->first();
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

    /**
     * Deteksi dan kaitkan satu atau beberapa wadah KIPAPP berdasarkan semua hashtag (#wilkerstat, #inovasi, dsb).
     */
    protected function resolveSharedWorkReports(User $user, string $text): \Illuminate\Support\Collection
    {
        if (empty($text)) {
            return collect();
        }

        $reports = collect();
        if (preg_match_all('/#([a-zA-Z0-9_\-]+)/', $text, $matches)) {
            foreach ($matches[1] as $tag) {
                $slug = \Illuminate\Support\Str::slug($tag);
                if (empty($slug)) continue;

                $found = \App\Models\SharedWorkReport::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($slug) {
                        $q->where('tag_slug', $slug)
                          ->orWhere('title', 'like', "%{$slug}%");
                    })
                    ->latest()
                    ->first();

                if ($found && !$reports->contains('id', $found->id)) {
                    $reports->push($found);
                }
            }
        }

        return $reports;
    }

    /**
     * Backward-compatible helper untuk mengambil wadah pertama.
     */
    protected function resolveSharedWorkReport(User $user, string $text): ?\App\Models\SharedWorkReport
    {
        return $this->resolveSharedWorkReports($user, $text)->first();
    }

    /**
     * Parse tanggal kegiatan secara cerdas (Natural Date Parsing):
     * - Mendukung kata relatif: "kemarin", "kemarin lusa", "X hari yang lalu"
     * - Mendukung pola nama bulan Indo: "28 Oktober", "tgl 5 mei 2026", "28 okt"
     * - Mendukung pola numerik: "28/10", "28-10-2026", "28.10.2026"
     * - Fallback ke metadata EXIF foto jika foto diambil di hari lampau
     * - Default ke Carbon::today()
     */
    public function resolveActivityDate(string $text, ?Carbon $exifCapturedAt = null): Carbon
    {
        $lower = strtolower(trim($text));

        // 1. Relatif: kemarin lusa
        if (preg_match('/\bkemarin\s+lusa\b/i', $lower)) {
            return Carbon::yesterday()->subDay()->startOfDay();
        }

        // 2. Relatif: kemarin
        if (preg_match('/\bkemarin\b/i', $lower)) {
            return Carbon::yesterday()->startOfDay();
        }

        // 3. Relatif: (\d+) hari (yang) lalu
        if (preg_match('/(\d+)\s*(?:hari|hr)\s*(?:yang\s*)?lalu/i', $lower, $m)) {
            $days = (int) $m[1];
            if ($days > 0 && $days <= 365) {
                return Carbon::today()->subDays($days)->startOfDay();
            }
        }

        // 4. Pola Nama Bulan Indonesia
        $monthMap = [
            'januari' => 1, 'jan' => 1,
            'februari' => 2, 'feb' => 2,
            'maret' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5,
            'juni' => 6, 'jun' => 6,
            'juli' => 7, 'jul' => 7,
            'agustus' => 8, 'agu' => 8, 'ags' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'okt' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'des' => 12,
        ];
        $monthPattern = implode('|', array_keys($monthMap));

        if (preg_match('/(?:tgl|tanggal)?\s*(\d{1,2})\s+(' . $monthPattern . ')(?:\s+(\d{4}))?/i', $lower, $m)) {
            $day = (int) $m[1];
            $month = $monthMap[strtolower($m[2])] ?? null;
            $year = !empty($m[3]) ? (int) $m[3] : (int) date('Y');

            if ($month && checkdate($month, $day, $year)) {
                return Carbon::createFromDate($year, $month, $day)->startOfDay();
            }
        }

        // 5. Pola Numerik: "28/10", "28/10/2026", "28-10-2026", "28.10.2026"
        if (preg_match('/(?:tgl|tanggal)?\s*(\d{1,2})[\/\-\.](\d{1,2})(?:[\/\-\.](\d{2,4}))?/i', $lower, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $rawYear = !empty($m[3]) ? (int) $m[3] : (int) date('Y');
            $year = ($rawYear < 100) ? (2000 + $rawYear) : $rawYear;

            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31 && checkdate($month, $day, $year)) {
                return Carbon::createFromDate($year, $month, $day)->startOfDay();
            }
        }

        // 6. Fallback EXIF metadata foto jika diambil sebelum hari ini (backdate foto asli)
        if ($exifCapturedAt instanceof Carbon && $exifCapturedAt->lt(Carbon::today())) {
            return $exifCapturedAt->copy()->startOfDay();
        }

        return Carbon::today();
    }
}
