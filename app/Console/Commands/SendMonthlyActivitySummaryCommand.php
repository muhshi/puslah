<?php

namespace App\Console\Commands;

use App\Models\DailyActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SendMonthlyActivitySummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-activity:send-monthly-recap {--dry-run : Uji coba tanpa mengirim pesan aktual}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim rekapan bulanan otomatis via Telegram yang merinci capaian hari kerja dan tanggal-tanggal yang belum ada laporannya.';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();

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
            $this->info("Belum ada hari kerja pada bulan ini.");
            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Query seluruh user yang memiliki telegram_chat_id
        $users = User::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->where('name', 'not like', '%Terlampir%')
            ->get();

        if ($users->isEmpty()) {
            $this->info("Tidak ada pengguna dengan akun Telegram yang terhubung.");
            return Command::SUCCESS;
        }

        $this->info("Memeriksa rekapan bulanan untuk {$users->count()} pengguna...");
        $monthName = $today->translatedFormat('F Y');

        foreach ($users as $user) {
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

            // Jika seluruhnya terisi, tidak perlu spam jika bukan request manual
            if (empty($missingDays)) {
                $this->line("User {$user->name}: 100% lengkap. Lewati pengingat.");
                continue;
            }

            $missingCount = count($missingDays);
            $percent = round(($filledCount / $totalWorkdays) * 100);

            $message = "📊 *Rekap Evaluasi Bukti Dukung Bulan {$monthName}*\n";
            $message .= "Halo *{$user->name}*, berikut rekapan hari kerja Anda:\n\n";
            $message .= "📈 *Capaian:* {$filledCount} dari {$totalWorkdays} hari kerja terisi (*{$percent}%*)\n";
            $message .= "⚠️ *{$missingCount} Hari Kerja Belum Ada Laporan:*\n";

            foreach ($missingDays as $md) {
                $message .= "• " . $md->translatedFormat('l, d F Y') . "\n";
            }

            $firstMissing = $missingDays[0]->format('d/m');
            $message .= "\n💡 _Segera lengkapi bukti dukung sebelum periode pelaporan ditutup. Anda bisa mengetik backdate (misal: \"tgl {$firstMissing}: kegiatan...\") atau mengirim foto dokumentasi._";

            if ($dryRun) {
                $this->info("[DRY RUN] Pesan untuk {$user->name} (Chat ID: {$user->telegram_chat_id}):\n{$message}\n");
            } else {
                try {
                    $bot->sendMessage(
                        text: $message,
                        chat_id: (int) $user->telegram_chat_id,
                        parse_mode: 'Markdown'
                    );
                    $this->info("Terkirim ke {$user->name} ({$missingCount} hari kosong).");
                } catch (\Throwable $e) {
                    $this->error("Gagal mengirim ke {$user->name}: " . $e->getMessage());
                    Log::error("Monthly recap telegram error for {$user->id}: " . $e->getMessage());
                }
            }
        }

        return Command::SUCCESS;
    }
}
