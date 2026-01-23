<?php

namespace App\Filament\Widgets;

use App\Models\SuratTugas;
use App\Models\LaporanPerjalananDinas;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class CalendarWidget extends FullCalendarWidget
{
    protected int|string|array $columnSpan = 'full';

    public Model|string|null $model = SuratTugas::class;

    public bool $showSuratTugas = true;
    public bool $showLPD = true;

    /**
     * FullCalendar structure for events.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $events = [];

        // 1. Surat Tugas Events
        if ($this->showSuratTugas) {
            $suratTugas = SuratTugas::query()
                ->where('waktu_mulai', '>=', $fetchInfo['start'])
                ->where('waktu_selesai', '<=', $fetchInfo['end'])
                ->with(['user', 'survey'])
                ->get();

            foreach ($suratTugas as $st) {
                $events[] = [
                    'id' => 'st-' . $st->id,
                    'title' => 'ST: ' . ($st->user->name ?? 'N/A') . ' - ' . ($st->survey->name ?? 'No Survey'),
                    'start' => $st->waktu_mulai,
                    'end' => $st->waktu_selesai,
                    'color' => '#3b82f6', // blue
                    'extendedProps' => [
                        'type' => 'Surat Tugas',
                        'survey_name' => $st->survey->name ?? 'No Survey',
                        'user_name' => $st->user->name ?? 'N/A',
                        'keperluan' => $st->keperluan,
                        'created_by' => $st->created_by,
                        'model_id' => $st->id,
                        'resource' => 'st',
                    ],
                ];
            }
        }

        // 2. Laporan Perjalanan Dinas (LPD) Events
        if ($this->showLPD) {
            $lpds = LaporanPerjalananDinas::query()
                ->where('tanggal_kunjungan', '>=', $fetchInfo['start'])
                ->where('tanggal_kunjungan', '<=', $fetchInfo['end'])
                ->with(['suratTugas.user'])
                ->get();

            foreach ($lpds as $lpd) {
                if (!$lpd->tanggal_kunjungan)
                    continue;

                $events[] = [
                    'id' => 'lpd-' . $lpd->id,
                    'title' => 'LPD: ' . ($lpd->suratTugas->user->name ?? 'N/A') . ' - ' . $lpd->tujuan,
                    'start' => \Carbon\Carbon::parse($lpd->tanggal_kunjungan)->toDateString(),
                    'allDay' => true,
                    'color' => '#10b981', // green
                    'extendedProps' => [
                        'type' => 'Laporan Perjalanan Dinas',
                        'user_name' => $lpd->suratTugas->user->name ?? 'N/A',
                        'tujuan' => $lpd->tujuan,
                        'model_id' => $lpd->id,
                        'resource' => 'lpd',
                    ],
                ];
            }
        }

        return $events;
    }

    public function onEventClick(array $info): void
    {
        $props = $info['event']['extendedProps'];
        $type = $props['type'];
        $userName = $props['user_name'];
        $modelId = $props['model_id'];
        $resourceType = $props['resource'];

        $startTime = \Carbon\Carbon::parse($info['event']['start'])->translatedFormat('d F Y H:i');
        $endTime = isset($info['event']['end']) ? \Carbon\Carbon::parse($info['event']['end'])->translatedFormat('d F Y H:i') : null;
        $timeRange = $endTime ? "$startTime s/d $endTime" : $startTime;

        $surveyInfo = $resourceType === 'st' ? "\n**Survey**: " . $props['survey_name'] : "";
        $detailInfo = $resourceType === 'st' ? "\n**Keperluan**: " . $props['keperluan'] : "\n**Tujuan**: " . $props['tujuan'];

        $canEdit = false;
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->hasAnyRole(['super_admin', 'Operator'])) {
            $canEdit = true;
        } elseif ($resourceType === 'st' && $user->hasRole('Ketua Tim') && $props['created_by'] == $user->id) {
            $canEdit = true;
        }

        $editUrl = $resourceType === 'st'
            ? \App\Filament\Resources\SuratTugasResource::getUrl('edit', ['record' => $modelId])
            : \App\Filament\Resources\LaporanPerjalananDinasResource::getUrl('edit', ['record' => $modelId]);

        \Filament\Notifications\Notification::make()
            ->title($type)
            ->body("**Petugas**: $userName$surveyInfo$detailInfo\n**Waktu**: $timeRange")
            ->actions([
                \Filament\Notifications\Actions\Action::make('edit')
                    ->label('Edit')
                    ->color('primary')
                    ->url($editUrl)
                    ->visible($canEdit),
                \Filament\Notifications\Actions\Action::make('close')
                    ->label('Tutup')
                    ->color('gray')
                    ->close(),
            ])
            ->send();
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }

    public function getFormSchema(): array
    {
        return [
            Grid::make()
                ->schema([
                    Toggle::make('showSuratTugas')
                        ->label('Tampilkan Surat Tugas')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(fn() => $this->refreshEvents()),
                    Toggle::make('showLPD')
                        ->label('Tampilkan Laporan Perjalanan Dinas')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(fn() => $this->refreshEvents()),
                ])
                ->columns(2),
        ];
    }

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'eventClick' => true,
        ];
    }
}
