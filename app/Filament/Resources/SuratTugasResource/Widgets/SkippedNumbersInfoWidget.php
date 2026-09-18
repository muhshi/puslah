<?php

namespace App\Filament\Resources\SuratTugasResource\Widgets;

use App\Models\BlockedSuratTugasNumber;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\SystemSettings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class SkippedNumbersInfoWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.resources.surat-tugas-resource.widgets.skipped-numbers-info';

    protected int|string|array $columnSpan = 'full';

    public int $selectedYear;

    public function mount(): void
    {
        $this->selectedYear = now()->year;
    }

    public function getSkippedNumbersByMonth(): array
    {
        return SuratTugas::getSkippedNumbersByMonth($this->selectedYear);
    }

    public function getBlockedNumbersGrouped(): array
    {
        return BlockedSuratTugasNumber::getBlockedNumbersGroupedByKeterangan($this->selectedYear);
    }

    public function getBlockedGroupsDetails(): array
    {
        $records = BlockedSuratTugasNumber::where('year', $this->selectedYear)
            ->orderBy('nomor_urut')
            ->get();

        $grouped = [];
        foreach ($records as $record) {
            $ket = $record->keterangan ?? 'Tanpa Keterangan';
            $grouped[$ket][] = $record;
        }

        $result = [];
        foreach ($grouped as $ket => $items) {
            $numbers = array_column($items, 'nomor_urut');
            $result[] = [
                'keterangan' => $ket,
                'ranges' => SuratTugas::formatSkippedNumbers($numbers),
                'count' => count($numbers),
                'numbers' => $numbers,
            ];
        }

        return $result;
    }

    public function getAvailableYears(): array
    {
        $years = SuratTugas::query()
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            return [now()->year => now()->year];
        }

        if (!in_array(now()->year, $years)) {
            array_unshift($years, now()->year);
        }

        return array_combine($years, $years);
    }

    public function updatedSelectedYear(): void
    {
        // Widget re-renders automatically when selectedYear changes
    }

    public function buatSuratTugasAction(): Action
    {
        return Action::make('buatSuratTugas')
            ->label('Buat ST')
            ->icon('heroicon-m-document-plus')
            ->color('success')
            ->modalHeading(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                return 'Buat Surat Tugas — ' . ($ket ? "({$ket})" : 'Nomor Terblokir');
            })
            ->modalDescription('Pilih nomor urut terblokir yang akan digunakan. Setelah Surat Tugas berhasil dibuat, nomor tersebut otomatis di-release.')
            ->modalSubmitActionLabel('Buat Surat Tugas')
            ->form(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                $query = BlockedSuratTugasNumber::where('year', $this->selectedYear);

                if ($ket === 'Tanpa Keterangan') {
                    $query->where(function ($sq) {
                        $sq->whereNull('keterangan')->orWhere('keterangan', '');
                    });
                } else {
                    $query->where('keterangan', $ket);
                }

                $blockedRecords = $query->orderBy('nomor_urut')->get();

                $options = [];
                $settings = app(SystemSettings::class);
                $prefix = $settings->surat_prefix ?? 'B';
                $office = $settings->office_code ?? '33210';

                foreach ($blockedRecords as $rec) {
                    $urut = str_pad($rec->nomor_urut, 4, '0', STR_PAD_LEFT);
                    $preview = "{$prefix}-{$urut}/{$office}/KP.650/{$rec->year}";
                    $options[$rec->id] = "Nomor #{$rec->nomor_urut} ({$preview})";
                }

                $firstRecordId = $blockedRecords->first()?->id;

                return \App\Filament\Resources\SuratTugasResource\Support\SuratTugasFormFactory::getBlockedNumberFormSchema(null, $options, $firstRecordId);
            })
            ->action(function (array $data) {
                $record = BlockedSuratTugasNumber::find($data['blocked_id'] ?? null);
                if (!$record) {
                    Notification::make()
                        ->title('Nomor terblokir tidak ditemukan')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    \App\Filament\Resources\SuratTugasResource\Support\SuratTugasFormFactory::createSuratTugasFromBlocked($record, $data);

                    Notification::make()
                        ->title('Surat Tugas berhasil dibuat!')
                        ->body("Nomor #{$record->nomor_urut} telah digunakan dan nomor terblokir otomatis di-release.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $body = str_starts_with($msg, 'Overlap:') ? substr($msg, 9) : $msg;
                    Notification::make()
                        ->title('Gagal membuat surat tugas')
                        ->body($body)
                        ->danger()
                        ->send();
                }
            });
    }

    public function releaseBlockedGroupAction(): Action
    {
        return Action::make('releaseBlockedGroup')
            ->label('Release Nomor')
            ->icon('heroicon-m-lock-open')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                return 'Release Nomor Terblokir — ' . ($ket ? "({$ket})" : '');
            })
            ->modalDescription('Apakah Anda yakin ingin membatalkan/merelease semua nomor yang terblokir pada grup ini agar bisa dipakai untuk surat tugas baru?')
            ->action(function (array $arguments) {
                $ket = $arguments['keterangan'] ?? '';
                $query = BlockedSuratTugasNumber::where('year', $this->selectedYear);

                if ($ket === 'Tanpa Keterangan') {
                    $query->where(function ($sq) {
                        $sq->whereNull('keterangan')->orWhere('keterangan', '');
                    });
                } else {
                    $query->where('keterangan', $ket);
                }

                $count = $query->count();
                $query->delete();

                Notification::make()
                    ->title("{$count} nomor terblokir berhasil di-release")
                    ->success()
                    ->send();
            });
    }
}

