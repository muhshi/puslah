<?php

namespace App\Filament\Widgets;

use App\Models\SuratTugas;
use App\Models\LaporanPerjalananDinas;
use Carbon\Carbon;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    protected int|string|array $columnSpan = 'full';

    public Model|string|null $model = SuratTugas::class;

    public bool $showSuratTugas = true;
    public bool $showLPD = true;

    /**
     * Fetch events for FullCalendar.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $events = [];

        if ($this->showSuratTugas) {
            $events = array_merge($events, $this->getSuratTugasEvents($fetchInfo));
        }

        if ($this->showLPD) {
            $events = array_merge($events, $this->getLPDEvents($fetchInfo));
        }

        return $events;
    }

    /**
     * Get Surat Tugas events.
     */
    private function getSuratTugasEvents(array $fetchInfo): array
    {
        $events = [];

        $suratTugasList = SuratTugas::query()
            ->where('waktu_mulai', '>=', $fetchInfo['start'])
            ->where('waktu_selesai', '<=', $fetchInfo['end'])
            ->with(['user', 'survey'])
            ->get();

        foreach ($suratTugasList as $st) {
            $events[] = [
                'id' => 'st-' . $st->id,
                'title' => 'ST: ' . ($st->user->name ?? 'N/A') . ' - ' . ($st->survey->name ?? 'No Survey'),
                'start' => $st->waktu_mulai,
                'end' => $st->waktu_selesai,
                'color' => '#3b82f6',
                'extendedProps' => [
                    'type' => 'Surat Tugas',
                    'survey_name' => $st->survey->name ?? 'No Survey',
                    'user_name' => $st->user->name ?? 'N/A',
                    'keperluan' => $st->keperluan ?? '-',
                    'created_by' => $st->created_by,
                    'model_id' => $st->id,
                    'resource' => 'st',
                ],
            ];
        }

        return $events;
    }

    /**
     * Get Laporan Perjalanan Dinas events.
     */
    private function getLPDEvents(array $fetchInfo): array
    {
        $events = [];

        $lpdList = LaporanPerjalananDinas::query()
            ->where('tanggal_kunjungan', '>=', $fetchInfo['start'])
            ->where('tanggal_kunjungan', '<=', $fetchInfo['end'])
            ->with(['suratTugas.user'])
            ->get();

        foreach ($lpdList as $lpd) {
            if (!$lpd->tanggal_kunjungan) {
                continue;
            }

            $events[] = [
                'id' => 'lpd-' . $lpd->id,
                'title' => 'LPD: ' . ($lpd->suratTugas->user->name ?? 'N/A') . ' - ' . $lpd->tujuan,
                'start' => Carbon::parse($lpd->tanggal_kunjungan)->toDateString(),
                'allDay' => true,
                'color' => '#10b981',
                'extendedProps' => [
                    'type' => 'Laporan Perjalanan Dinas',
                    'user_name' => $lpd->suratTugas->user->name ?? 'N/A',
                    'tujuan' => $lpd->tujuan ?? '-',
                    'model_id' => $lpd->id,
                    'resource' => 'lpd',
                ],
            ];
        }

        return $events;
    }

    /**
     * Handle event click - show detail notification with Edit button.
     */
    /**
     * Handle event click - show detail notification with Edit button.
     */
    public function onEventClick(array $info): void
    {
        // In v3, $info contains the event data directly including extendedProps
        $props = $info['extendedProps'] ?? [];

        $type = $props['type'] ?? 'Event';
        $userName = $props['user_name'] ?? 'N/A';
        $modelId = $props['model_id'] ?? null;
        $resourceType = $props['resource'] ?? '';

        $startTime = Carbon::parse($info['start'] ?? now())->translatedFormat('d F Y H:i');
        $endTime = isset($info['end']) ? Carbon::parse($info['end'])->translatedFormat('d F Y H:i') : null;
        $timeRange = $endTime ? "{$startTime} s/d {$endTime}" : $startTime;

        $surveyInfo = ($resourceType === 'st') ? "\n**Survey**: " . ($props['survey_name'] ?? '-') : '';
        $detailInfo = ($resourceType === 'st')
            ? "\n**Keperluan**: " . ($props['keperluan'] ?? '-')
            : "\n**Tujuan**: " . ($props['tujuan'] ?? '-');

        $canEdit = $this->checkEditPermission($resourceType, $props);

        $editUrl = $this->getEditUrl($resourceType, $modelId);

        Notification::make()
            ->title($type)
            ->body("**Petugas**: {$userName}{$surveyInfo}{$detailInfo}\n**Waktu**: {$timeRange}")
            ->actions([
                NotificationAction::make('edit')
                    ->label('Edit')
                    ->color('primary')
                    ->url($editUrl)
                    ->visible($canEdit && $editUrl !== null),
                NotificationAction::make('close')
                    ->label('Tutup')
                    ->color('gray')
                    ->close(),
            ])
            ->send();
    }

    /**
     * Check if the current user can edit the event.
     */
    private function checkEditPermission(string $resourceType, array $props): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'Operator'])) {
            return true;
        }

        if ($resourceType === 'st' && $user->hasRole('Ketua Tim')) {
            return ($props['created_by'] ?? null) == $user->id;
        }

        return false;
    }

    /**
     * Get the edit URL for the resource.
     */
    private function getEditUrl(string $resourceType, ?int $modelId): ?string
    {
        if (!$modelId) {
            return null;
        }

        return match ($resourceType) {
            'st' => \App\Filament\Resources\SuratTugasResource::getUrl('edit', ['record' => $modelId]),
            'lpd' => \App\Filament\Resources\LaporanPerjalananDinasResource::getUrl('edit', ['record' => $modelId]),
            default => null,
        };
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
        ];
    }
}

