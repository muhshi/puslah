<?php

namespace App\Console\Commands;

use App\Models\DailyActivity;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SendDailyActivityReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-activity:send-reminders {--tier=15:00 : Jam tier reminder: 15:00, 16:00, atau 18:30} {--dry-run : Uji coba tanpa mengirim pesan aktual}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat Telegram untuk pegawai yang belum mencatat kegiatan hari ini pada jam 15.00, 16.00, dan 18.30 (kecuali hari libur).';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        $today = Carbon::today();

        // 1. Cek Hari Libur Akhir Pekan (Sabtu & Minggu)
        if ($today->isWeekend()) {
            $this->info("Hari ini akhir pekan ({$today->translatedFormat('l')}). Pengingat tidak dikirim.");
            return Command::SUCCESS;
        }

        $tier = $this->option('tier') ?: '15:00';
        $dryRun = (bool) $this->option('dry-run');

        // 2. Query seluruh user yang memiliki telegram_chat_id
        $users = User::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->where('name', 'not like', '%Terlampir%')
            ->get();

        if ($users->isEmpty()) {
            $this->info("Tidak ada pengguna dengan akun Telegram yang terhubung.");
            return Command::SUCCESS;
        }

        $this->info("Memeriksa log kegiatan untuk {$users->count()} pengguna pada tier {$tier} WIB...");

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Cek apakah user sedang cuti yang telah disetujui hari ini
            $isOnLeave = Leave::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            if ($isOnLeave) {
                $this->line("[-] User {$user->name} sedang cuti. Skip.");
                $skippedCount++;
                continue;
            }

            // Cek apakah user sudah mengisi kegiatan hari ini
            $activityCount = DailyActivity::query()
                ->where('user_id', $user->id)
                ->whereDate('activity_date', $today)
                ->count();

            if ($activityCount > 0) {
                $this->line("[-] User {$user->name} sudah memiliki {$activityCount} kegiatan hari ini. Skip.");
                $skippedCount++;
                continue;
            }

            // Susun pesan sesuai tier
            $message = $this->buildMessage($user, $tier);

            if ($dryRun) {
                $this->warn("[DRY-RUN] Akan kirim ke {$user->name} (Chat ID: {$user->telegram_chat_id}):");
                $this->line($message);
                $sentCount++;
                continue;
            }

            try {
                $bot->sendMessage(
                    text: $message,
                    chat_id: (int) $user->telegram_chat_id,
                    parse_mode: 'Markdown'
                );

                $this->info("[+] Berhasil kirim pengingat ke {$user->name}");
                $sentCount++;
            } catch (\Throwable $e) {
                $this->error("[!] Gagal kirim ke {$user->name}: " . $e->getMessage());
                Log::error("Reminder Telegram error for {$user->name}: " . $e->getMessage());
            }
        }

        $this->info("Selesai. Terkirim: {$sentCount}, Dilewati: {$skippedCount}");
        return Command::SUCCESS;
    }

    /**
     * Format template pesan sesuai jam pengingat.
     */
    protected function buildMessage(User $user, string $tier): string
    {
        $firstName = explode(' ', trim($user->name))[0];

        switch ($tier) {
            case '16:00':
                return "⏰ *Pengingat Jam Pulang Kantor (16:00 WIB)*\n\n" .
                    "Halo *{$firstName}*! 👋\n" .
                    "Jam kerja kantor akan segera berakhir dan catatan bukti dukung Anda untuk hari ini masih kosong.\n\n" .
                    "Sempatkan 1 menit untuk mengetik kegiatan atau mengirimkan foto dokumentasi ke bot ini sebelum pulang ya! 😊";

            case '18:30':
                return "🌙 *Pengingat Malam (18:30 WIB)*\n\n" .
                    "Halo *{$firstName}*! 👋\n" .
                    "Rekap laporan harian hari ini masih belum terisi.\n\n" .
                    "Yuk laporkan pekerjaan atau foto kegiatan hari ini agar besok tidak menumpuk atau lupa detail pekerjaannya.";

            case '15:00':
            default:
                return "👋 *Pengingat Laporan Harian (15:00 WIB)*\n\n" .
                    "Halo *{$firstName}*! Belum ada catatan pekerjaan atau bukti dukung yang tercatat untuk hari ini.\n\n" .
                    "Sambil santai sore, yuk kirimkan foto kegiatan atau ketik singkat apa yang Anda kerjakan hari ini ke bot ini!";
        }
    }
}
