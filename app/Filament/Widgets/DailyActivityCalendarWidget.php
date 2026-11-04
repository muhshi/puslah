<?php

namespace App\Filament\Widgets;

use App\Models\DailyActivity;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class DailyActivityCalendarWidget extends FullCalendarWidget implements HasActions
{
    use InteractsWithActions;

    protected int|string|array $columnSpan = 'full';

    public Model|string|null $model = DailyActivity::class;

    /**
     * Matikan tombol create bawaan FullCalendar widget agar tidak muncul tombol kosong.
     */
    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }

    /**
     * Konfigurasi FullCalendar.
     */
    public function config(): array
    {
        return [
            'initialView' => 'dayGridMonth',
            'selectable' => true,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,listMonth',
            ],
            'buttonText' => [
                'today' => 'Hari Ini',
                'month' => 'Bulan',
                'list' => 'Daftar',
            ],
        ];
    }

    /**
     * Ambil event aktivitas harian user.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $user = Auth::user();
        if (!$user) return [];

        $activities = DailyActivity::query()
            ->where('user_id', $user->id)
            ->whereBetween('activity_date', [$fetchInfo['start'], $fetchInfo['end']])
            ->with(['suratTugas', 'attachments'])
            ->get();

        $events = [];

        foreach ($activities as $act) {
            $isLpd = !empty($act->laporan_perjalanan_dinas_id);
            $hasSt = !empty($act->surat_tugas_id);
            $photoCount = $act->attachments->count();
            $photoIcon = $photoCount > 0 ? " 📷{$photoCount}" : "";

            // Skema warna:
            // Ungu: Sudah disalin ke LPD
            // Biru: Terkait Surat Tugas
            // Hijau: Kegiatan Rutin Harian
            $color = $isLpd ? '#8B5CF6' : ($hasSt ? '#3B82F6' : '#10B981');
            $prefix = $isLpd ? '[LPD] ' : ($hasSt ? '[ST] ' : '');

            $shortTitle = $prefix . mb_strimwidth($act->final_content, 0, 45, '...') . $photoIcon;

            $events[] = [
                'id' => 'act-' . $act->id,
                'title' => $shortTitle,
                'start' => Carbon::parse($act->activity_date)->toDateString(),
                'allDay' => true,
                'color' => $color,
                'url' => route('filament.admin.resources.daily-activities.edit', ['record' => $act->id]),
                'extendedProps' => [
                    'content' => $act->final_content,
                    'st_number' => $act->suratTugas?->nomor_surat_tugas,
                    'photo_count' => $photoCount,
                ],
            ];
        }

        // Tambahkan penanda visual untuk hari kerja lampau (Senin - Jumat) yang belum diisi kegiatan
        $today = Carbon::today();
        $startDate = Carbon::parse($fetchInfo['start'])->startOfDay();
        $endDate = Carbon::parse($fetchInfo['end'])->endOfDay();
        $maxPastDate = $today->copy()->subDay()->endOfDay();

        $filledDatesMap = $activities->pluck('activity_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip()
            ->toArray();

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate) && $cursor->lte($maxPastDate)) {
            $dateStr = $cursor->toDateString();
            if (!$cursor->isWeekend() && !isset($filledDatesMap[$dateStr])) {
                $events[] = [
                    'id' => 'missing-' . $dateStr,
                    'title' => '⚠️ Kosong: Belum Ada Bukti',
                    'start' => $dateStr,
                    'allDay' => true,
                    'color' => '#F59E0B',
                    'url' => route('filament.admin.resources.daily-activities.create', ['activity_date' => $dateStr]),
                    'extendedProps' => [
                        'is_empty_workday' => true,
                    ],
                ];
            }
            $cursor->addDay();
        }

        return $events;
    }

    /**
     * Klik pada tanggal kosong di kalender -> Buka form tambah kegiatan dengan tanggal terisi otomatis!
     */
    public function onDateSelect(string $start, ?string $end, bool $allDay, ?array $view, ?array $resource): void
    {
        $date = Carbon::parse($start)->format('Y-m-d');
        $this->redirect(route('filament.admin.resources.daily-activities.create', ['activity_date' => $date]));
    }
}
