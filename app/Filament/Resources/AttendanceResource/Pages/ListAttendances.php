<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rekap_belum_presensi')
                ->label('Rekap Belum Presensi')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->badge(function () {
                    $count = self::getUnattendedCountToday();
                    return $count > 0 ? "{$count} Belum" : null;
                })
                ->badgeColor('danger')
                ->modalHeading('Rekap Belum Presensi & Pengingat WhatsApp')
                ->modalDescription('Daftar pegawai/petugas yang belum melakukan presensi hari ini. Anda dapat menyalin daftar pengingat atau mengirim pesan WhatsApp secara langsung.')
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(function () {
                    $tableFilters = $this->tableFilters['survey_id']['value'] ?? null;
                    return view('filament.resources.attendance-resource.unattended-modal', [
                        'surveyId' => $tableFilters ? (int) $tableFilters : null,
                        'date' => Carbon::today('Asia/Jakarta')->toDateString(),
                    ]);
                }),

            Action::make('Tambah Presensi')
                ->url(route('presensi'))
                ->color('success'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\TodayAttendanceStats::class,
        ];
    }

    public static function getUnattendedCountToday(): int
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        $activeSurveyIds = Survey::where('is_active', true)->pluck('id');
        $suIds = SurveyUser::whereIn('survey_id', $activeSurveyIds)->pluck('user_id');
        $stIds = SuratTugas::whereIn('survey_id', $activeSurveyIds)->pluck('user_id');
        $organikIds = User::whereHas('roles', fn($q) => $q->whereIn('name', ['Organik', 'Kepala', 'Kasubag', 'Ketua Tim', 'Operator', 'IPDS', 'Pengolahan']))->pluck('id');

        $allTargetIds = $suIds->merge($stIds)->merge($organikIds)->unique()->filter();

        if ($allTargetIds->isEmpty()) {
            return 0;
        }

        $attendedIds = Attendance::whereDate('created_at', $today)
            ->whereIn('user_id', $allTargetIds)
            ->pluck('user_id')
            ->unique();

        $leaveIds = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereIn('user_id', $allTargetIds)
            ->pluck('user_id')
            ->unique();

        return $allTargetIds->diff($attendedIds)->diff($leaveIds)->count();
    }
}
