<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AttendanceResource;
use App\Filament\Resources\LeaveResource;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class TodayAttendanceStats extends BaseWidget
{
    protected ?string $heading = 'Rekap Hari Ini (WIB)';
    protected function getStats(): array
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // Total user “aktif” → kalau mau pakai user_profiles.employment_status = 'aktif', ganti query ini
        $totalUsers = User::query()
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where(function ($q) {
                $q->whereNull('user_profiles.employment_status')
                    ->orWhere('user_profiles.employment_status', 'aktif');
            })
            ->count('users.id');

        // Hadir (distinct user) berdasarkan Attendance hari ini
        $hadir = Attendance::whereDate('created_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        // Izin/Cuti (distinct user) overlap hari ini
        $izin = Leave::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->distinct('user_id')
            ->count('user_id');

        // Belum presensi = total - hadir - izin
        $belum = max($totalUsers - $hadir - $izin, 0);
        return [
            Stat::make('Masuk hari ini', number_format($hadir))
                ->description('Check-in unik')
                ->color('success')
                ->url(AttendanceResource::getUrl()),

            Stat::make('Belum presensi', number_format($belum))
                ->description('Tidak termasuk izin/cuti')
                ->color('danger')
                ->url(AttendanceResource::getUrl()),

            Stat::make('Izin/Cuti', number_format($izin))
                ->description('Approved yang aktif hari ini')
                ->color('warning')
                ->url(LeaveResource::getUrl()),
        ];
    }
}
