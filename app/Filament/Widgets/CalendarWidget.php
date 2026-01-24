<?php

namespace App\Filament\Widgets;

use App\Models\SuratTugas;
use App\Models\LaporanPerjalananDinas;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget implements HasActions, HasInfolists
{
    use InteractsWithActions;
    use InteractsWithInfolists;

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

    private function getSuratTugasEvents(array $fetchInfo): array
    {
        $events = [];

        $suratTugasList = SuratTugas::query()
            ->where('waktu_mulai', '>=', $fetchInfo['start'])
            ->where('waktu_selesai', '<=', $fetchInfo['end'])
            ->where('user_id', auth()->id())
            ->whereHas('user.roles', function ($query) {
                $query->where('name', '!=', 'Mitra');
            })
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

    private function getLPDEvents(array $fetchInfo): array
    {
        $events = [];

        $lpdList = LaporanPerjalananDinas::query()
            ->where('tanggal_kunjungan', '>=', $fetchInfo['start'])
            ->where('tanggal_kunjungan', '<=', $fetchInfo['end'])
            ->whereHas('suratTugas', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->whereHas('suratTugas.user.roles', function ($query) {
                $query->where('name', '!=', 'Mitra');
            })
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
     * Handle event click - trigger the detail modal action.
     */
    public function onEventClick(array $info): void
    {
        $this->mountAction('viewEvent', [
            'info' => $info,
        ]);
    }

    /**
     * Define the detail modal action.
     */
    public function viewEventAction(): Action
    {
        return Action::make('viewEvent')
            ->modalHeading(fn(array $arguments) => $arguments['info']['extendedProps']['type'] ?? 'Detail Event')
            ->modalWidth('md')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->infolist(function (Infolist $infolist, array $arguments): Infolist {
                $info = $arguments['info'] ?? [];
                $props = $info['extendedProps'] ?? [];
                $resourceType = $props['resource'] ?? '';

                $startTime = Carbon::parse($info['start'] ?? now())->translatedFormat('d F Y H:i');
                $endTime = isset($info['end']) ? Carbon::parse($info['end'])->translatedFormat('d F Y H:i') : null;
                $timeRange = $endTime ? "{$startTime} s/d {$endTime}" : $startTime;

                return $infolist
                    ->state([
                        'petugas' => $props['user_name'] ?? '-',
                        'survey' => $props['survey_name'] ?? '-',
                        'keperluan' => ($resourceType === 'st') ? ($props['keperluan'] ?? '-') : ($props['tujuan'] ?? '-'),
                        'waktu' => $timeRange,
                    ])
                    ->schema([
                        Section::make()
                            ->schema([
                                TextEntry::make('petugas')
                                    ->label('Petugas'),

                                TextEntry::make('survey')
                                    ->label('Survey')
                                    ->visible($resourceType === 'st'),

                                TextEntry::make('keperluan')
                                    ->label($resourceType === 'st' ? 'Keperluan' : 'Tujuan'),

                                TextEntry::make('waktu')
                                    ->label('Waktu'),
                            ])
                            ->columns(1),
                    ]);
            })
            ->extraModalFooterActions(function (array $arguments): array {
                $info = $arguments['info'] ?? [];
                $props = $info['extendedProps'] ?? [];
                $resourceType = $props['resource'] ?? '';
                $modelId = $props['model_id'] ?? null;

                $canEdit = $this->checkEditPermission($resourceType, $props);
                $editUrl = $this->getEditUrl($resourceType, $modelId);

                if (!$canEdit || !$editUrl) {
                    return [];
                }

                return [
                    Action::make('edit')
                        ->label('Edit Selengkapnya')
                        ->color('primary')
                        ->url($editUrl),
                ];
            });
    }

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
        return [
            Action::make('toggleSuratTugas')
                ->label($this->showSuratTugas ? 'Sembunyikan Surat Tugas' : 'Tampilkan Surat Tugas')
                ->icon($this->showSuratTugas ? 'heroicon-m-eye' : 'heroicon-m-eye-slash')
                ->color($this->showSuratTugas ? 'info' : 'gray')
                ->action(function () {
                    $this->showSuratTugas = !$this->showSuratTugas;
                    $this->refreshRecords();
                }),
            Action::make('toggleLPD')
                ->label($this->showLPD ? 'Sembunyikan LPD' : 'Tampilkan LPD')
                ->icon($this->showLPD ? 'heroicon-m-eye' : 'heroicon-m-eye-slash')
                ->color($this->showLPD ? 'success' : 'gray')
                ->action(function () {
                    $this->showLPD = !$this->showLPD;
                    $this->refreshRecords();
                }),
        ];
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
                        ->afterStateUpdated(fn() => $this->refreshRecords()),
                    Toggle::make('showLPD')
                        ->label('Tampilkan Laporan Perjalanan Dinas')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(fn() => $this->refreshRecords()),
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
            'selectable' => false,
            'editable' => false,
        ];
    }
}
