<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RebuildAttendanceDailyRecap extends Command
{
    protected $signature = 'recap:rebuild {--from=} {--to=} {--survey=}';
    protected $description = 'Rebuild attendance daily recap for a date range; optionally limit to a survey participants.';

    public function handle(): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : null;

        if (!$from || !$to || $from->gt($to)) {
            $this->error('Use: --from=YYYY-MM-DD --to=YYYY-MM-DD');
            return self::FAILURE;
        }

        $surveyId = $this->option('survey') ? (int) $this->option('survey') : null;
        $userIds = null;

        if ($surveyId) {
            // Ambil peserta survey untuk mempersempit proses (lebih cepat)
            $userIds = DB::table('survey_users')->where('survey_id', $surveyId)->pluck('user_id')->all();
            if (empty($userIds)) {
                $this->warn("Survey $surveyId tidak punya peserta.");
                return self::SUCCESS;
            }
        }

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            // Panggil recap:update per hari; kalau ada userIds, kirimkan via --user (boleh banyak)
            if ($userIds) {
                // kirim per chunk agar argumen tidak kepanjangan
                foreach (array_chunk($userIds, 200) as $chunk) {
                    $args = array_reduce($chunk, fn($carry, $id) => $carry . " --user={$id}", '');
                    $cmd = "recap:update --date={$d->toDateString()}{$args}";
                    $this->callSilent($cmd);
                }
            } else {
                $this->callSilent('recap:update', ['--date' => $d->toDateString()]);
            }
            $this->info("Rekap: " . $d->toDateString());
        }

        $this->info('Selesai rebuild recap.');
        return self::SUCCESS;
    }
}
