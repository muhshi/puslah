<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateAttendanceDailyRecap extends Command
{
    protected $signature = 'recap:update {--date=} {--user=*}';
    protected $description = 'Update attendance daily recap (default: yesterday, all users)';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now('Asia/Jakarta')->subDay()->startOfDay();

        $dow = (int) $date->dayOfWeekIso; // 1=Mon..7=Sun
        $isWorkday = ($dow >= 1 && $dow <= 5) ? 1 : 0;
        $dateStr = $date->toDateString();

        $userIdsOpt = $this->option('user'); // array of IDs (optional)

        // --- KUMPULKAN KANDIDAT USER ---
        // 1) peserta survey yang aktif di tanggal tsb
        $participants = DB::table('survey_users AS su')
            ->join('surveys AS s', 's.id', '=', 'su.survey_id')
            ->whereDate('s.start_date', '<=', $dateStr)
            ->whereDate('s.end_date', '>=', $dateStr)
            ->pluck('su.user_id');

        // 2) yang punya attendance di tanggal tsb
        $attenders = DB::table('attendances')
            ->whereDate('start_time', $dateStr)
            ->pluck('user_id');

        // 3) yang punya cuti overlap tanggal tsb
        $leavers = DB::table('leaves')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->pluck('user_id');

        // Union semuanya
        $candidates = collect()
            ->merge($participants)
            ->merge($attenders)
            ->merge($leavers)
            ->unique()
            ->values();

        // Kalau user option diberikan, batasi ke mereka
        $userIds = $userIdsOpt && count($userIdsOpt) ? $candidates->intersect($userIdsOpt)->values()->all()
            : $candidates->all();

        if (empty($userIds)) {
            $this->info("Tidak ada user yang perlu direkap untuk $dateStr");
            return self::SUCCESS;
        }

        foreach (array_chunk($userIds, 500) as $chunk) {
            $idsIn = implode(',', array_fill(0, count($chunk), '?'));

            $sql = "
            INSERT INTO attendance_daily_recaps
                (user_id, work_date, is_workday, present, late, under_7h, no_checkout, `leave`, alpa, created_at, updated_at)
            SELECT
                u.id,
                ? as work_date,
                ? as is_workday,

                -- HADIR: ada attendance yang dibuat pada tanggal tsb
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                FROM attendances a
                WHERE a.user_id = u.id
                AND DATE(a.created_at) = ?) as present,

                -- TERLAMBAT: pada tanggal tsb & start_time > schedule_start_time
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                FROM attendances a
                WHERE a.user_id = u.id
                AND DATE(a.created_at) = ?
                AND a.start_time > a.schedule_start_time) as late,

                -- < 7 JAM: pada tanggal tsb & end_time ada & durasi < 420 menit
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                FROM attendances a
                WHERE a.user_id = u.id
                AND DATE(a.created_at) = ?
                AND a.end_time IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) < 420) as under_7h,

                -- TIDAK CHECKOUT: pada tanggal tsb & end_time null
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                FROM attendances a
                WHERE a.user_id = u.id
                AND DATE(a.created_at) = ?
                AND a.end_time IS NULL) as no_checkout,

                -- CUTI: overlap dengan tanggal tsb
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                FROM leaves l
                WHERE l.user_id = u.id
                AND l.status = 'approved'
                AND l.start_date <= ?
                AND l.end_date   >= ?) as `leave`,

                -- ALPA: hari kerja & tidak hadir & tidak cuti
                CASE
                WHEN ? = 1
                    AND (SELECT COUNT(*) FROM attendances a2
                        WHERE a2.user_id = u.id
                        AND DATE(a2.created_at) = ?) = 0
                    AND (SELECT COUNT(*) FROM leaves l2
                        WHERE l2.user_id = u.id
                        AND l2.status = 'approved'
                        AND l2.start_date <= ?
                        AND l2.end_date   >= ?) = 0
                THEN 1 ELSE 0
                END as alpa,

                NOW(), NOW()
            FROM users u
            WHERE u.id IN ($idsIn)
            ON DUPLICATE KEY UPDATE
                is_workday = VALUES(is_workday),
                present    = VALUES(present),
                late       = VALUES(late),
                under_7h   = VALUES(under_7h),
                no_checkout= VALUES(no_checkout),
                `leave`    = VALUES(`leave`),
                alpa       = VALUES(alpa),
                updated_at = VALUES(updated_at)
            ";

            $params = [
                $dateStr,
                $isWorkday,
                $dateStr, // present
                $dateStr, // late
                $dateStr, // under_7h
                $dateStr, // no_checkout
                $dateStr,
                $dateStr, // leave overlap
                $isWorkday,
                $dateStr,
                $dateStr,
                $dateStr, // alpa check
                ...$chunk,
            ];

            DB::statement($sql, $params);
        }

        $this->info("Rekap selesai untuk $dateStr (" . count($userIds) . " user)");
        return self::SUCCESS;
    }

}
