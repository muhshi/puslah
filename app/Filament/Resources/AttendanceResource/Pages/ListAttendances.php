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
                ->label('Rekap Presensi Kegiatan')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->badge(function () {
                    $tableFilters = $this->tableFilters['survey_id']['value'] ?? null;
                    $stats = self::getAttendanceStatusToday($tableFilters ? (int) $tableFilters : null);
                    if ($stats['total'] === 0) {
                        return null;
                    }
                    if ($stats['belum_datang'] > 0) {
                        return "{$stats['belum_datang']} Belum Datang";
                    }
                    if ($stats['belum_pulang'] > 0) {
                        return "{$stats['belum_pulang']} Belum Pulang";
                    }
                    return "Lengkap ({$stats['total']})";
                })
                ->badgeColor(function () {
                    $tableFilters = $this->tableFilters['survey_id']['value'] ?? null;
                    $stats = self::getAttendanceStatusToday($tableFilters ? (int) $tableFilters : null);
                    if ($stats['belum_datang'] > 0) {
                        return 'danger';
                    }
                    if ($stats['belum_pulang'] > 0) {
                        return 'warning';
                    }
                    return 'success';
                })
                ->modalHeading('Pemantauan Presensi Petugas Kegiatan')
                ->modalDescription('Pantau presensi datang dan pulang petugas kegiatan setiap hari, dan ingatkan langsung via WhatsApp.')
                ->modalWidth(MaxWidth::FourExtraLarge)
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

    public static function getAttendanceStatusToday(?int $surveyId = null): array
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        if (!$surveyId) {
            $surveyId = Survey::where('is_active', true)
                ->whereHas('participants', function ($q) use ($today) {
                    $q->whereHas('attendances', fn($aq) => $aq->whereDate('created_at', $today));
                })
                ->latest('id')
                ->value('id')
                ?? Survey::where('name', 'like', '%Pengolahan Pemutakhiran Kerangka Geospasial%')->value('id')
                ?? Survey::where('is_active', true)->has('participants')->latest('id')->value('id');
        }

        if (!$surveyId) {
            return ['total' => 0, 'belum_datang' => 0, 'belum_pulang' => 0, 'lengkap' => 0];
        }

        $survey = Survey::find($surveyId);
        if (!$survey) {
            return ['total' => 0, 'belum_datang' => 0, 'belum_pulang' => 0, 'lengkap' => 0];
        }

        $participantIds = $survey->participants()->pluck('users.id');
        if ($participantIds->isEmpty()) {
            return ['total' => 0, 'belum_datang' => 0, 'belum_pulang' => 0, 'lengkap' => 0];
        }

        $attendances = Attendance::whereDate('created_at', $today)
            ->whereIn('user_id', $participantIds)
            ->get()
            ->keyBy('user_id');

        $leaves = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereIn('user_id', $participantIds)
            ->pluck('user_id')
            ->all();

        $belumDatang = 0;
        $belumPulang = 0;
        $lengkap = 0;

        foreach ($participantIds as $uid) {
            if (in_array($uid, $leaves)) {
                continue;
            }
            $att = $attendances->get($uid);
            if (!$att) {
                $belumDatang++;
            } elseif (empty($att->end_time)) {
                $belumPulang++;
            } else {
                $lengkap++;
            }
        }

        return [
            'total' => $participantIds->count(),
            'belum_datang' => $belumDatang,
            'belum_pulang' => $belumPulang,
            'lengkap' => $lengkap,
        ];
    }
}
