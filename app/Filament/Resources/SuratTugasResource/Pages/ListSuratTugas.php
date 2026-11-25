<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Carbon\Carbon;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\HtmlString;
use App\Imports\SuratTugasImport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use App\Filament\Resources\SuratTugasResource\Widgets\SkippedNumbersInfoWidget;
use App\Filament\Resources\SuratTugasResource;
use App\Models\SuratTugas;
use App\Models\Survey;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSuratTugas extends ListRecords
{
    protected static string $resource = SuratTugasResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => SuratTugas::where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn () => SuratTugas::where('status', 'approved')->count())
                ->badgeColor('success'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_surat_tugas')
                ->label('Import Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->schema([
                    Select::make('survey_id')
                        ->label('Pilih Survey')
                        ->options(function () {
                            return Survey::where('is_active', true)
                                ->orderByDesc('created_at')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if ($state) {
                                $survey = Survey::find($state);
                                if ($survey) {
                                    $set('keperluan', "{$survey->name}");
                                    if ($survey->start_date) {
                                        $set('waktu_mulai', Carbon::parse($survey->start_date)->format('Y-m-d'));
                                    }
                                    if ($survey->end_date) {
                                        $set('waktu_selesai', Carbon::parse($survey->end_date)->format('Y-m-d'));
                                    }
                                }
                            }
                        })
                        ->required(),
                    Group::make()->schema([
                        DatePicker::make('tanggal')
                            ->label('Tanggal Surat')
                            ->default(now()->format('Y-m-d'))
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $year = Carbon::parse($state)->year;
                                    $nextNumber = SuratTugas::getNextNomorUrut($year);
                                    $set('nomor_urut_mulai', $nextNumber);
                                }
                            })
                            ->required(),
                        DatePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->default(now()->format('Y-m-d')),
                        DatePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->default(now()->format('Y-m-d')),
                    ])->columns(3),
                    Textarea::make('keperluan')
                        ->label('Keperluan')
                        ->required(),
                    TextInput::make('nomor_urut_mulai')
                        ->label('Nomor Urut Mulai')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(fn() => SuratTugas::getNextNomorUrut(now()->year)),
                    FileUpload::make('file_excel')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                        ->directory('imports')
                        ->required()
                        ->helperText(new HtmlString('Download template: <a href="/templates/surat_tugas_import_template.xlsx" target="_blank" class="text-primary-600 hover:underline">surat_tugas_import_template.xlsx</a>')),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/public/' . $data['file_excel']);
                    $import = new SuratTugasImport(
                        $data['survey_id'],
                        $data['tanggal'],
                        $data['waktu_mulai'],
                        $data['waktu_selesai'],
                        $data['keperluan'],
                        'KP.650',
                        $data['nomor_urut_mulai'] ?? null
                    );
                    Excel::import($import, $filePath);

                    Notification::make()
                        ->title('Import Selesai')
                        ->body("Berhasil: {$import->success}, Dilewati: {$import->skipped}, Gagal: {$import->failed}")
                        ->success()
                        ->send();
                }),
            Action::make('create-bulk')
                ->label('Buat Kolektif')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(fn() => SuratTugasResource::getUrl('create-bulk')),
            Action::make('manage-blocked-numbers')
                ->label('Block Nomor')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->url(fn() => SuratTugasResource::getUrl('manage-blocked-numbers')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SkippedNumbersInfoWidget::class,
        ];
    }
}
