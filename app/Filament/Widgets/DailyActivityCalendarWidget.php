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
